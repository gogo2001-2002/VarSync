<?php

declare(strict_types=1);

class VariableSync extends IPSModule
{
    private const VM_UPDATE = 10603;

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->RegisterPropertyString('SyncList', '[]');
    }

    public function ApplyChanges()
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        // Alle bisherigen Nachrichten-Registrierungen aufheben
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        $syncList = json_decode($this->ReadPropertyString('SyncList'), true);
        if (!is_array($syncList)) {
            return;
        }

        $registeredVars = [];

        foreach ($syncList as $entry) {
            if (empty($entry['Active'])) {
                continue;
            }

            $varA = (int)($entry['VariableA'] ?? 0);
            $varB = (int)($entry['VariableB'] ?? 0);
            $direction = (int)($entry['SyncDirection'] ?? 0);

            if ($varA <= 0 || $varB <= 0 || $varA === $varB) {
                continue;
            }

            // Auf Variable A lauschen, wenn Richtung A <-> B (0) oder A -> B (1)
            if (($direction === 0 || $direction === 1) && !isset($registeredVars[$varA]) && IPS_VariableExists($varA)) {
                $this->RegisterMessage($varA, self::VM_UPDATE);
                $registeredVars[$varA] = true;
            }

            // Auf Variable B lauschen, wenn Richtung A <-> B (0) oder B -> A (2)
            if (($direction === 0 || $direction === 2) && !isset($registeredVars[$varB]) && IPS_VariableExists($varB)) {
                $this->RegisterMessage($varB, self::VM_UPDATE);
                $registeredVars[$varB] = true;
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message !== self::VM_UPDATE) {
            return;
        }

        // $Data[1] ist true, wenn sich der Wert tatsächlich geändert hat (nicht nur Aktualisierung)
        $valueChanged = $Data[1];
        if (!$valueChanged) {
            return;
        }

        $newValue = $Data[0];
        $syncList = json_decode($this->ReadPropertyString('SyncList'), true);
        if (!is_array($syncList)) {
            return;
        }

        foreach ($syncList as $entry) {
            if (empty($entry['Active'])) {
                continue;
            }

            $varA = (int)($entry['VariableA'] ?? 0);
            $varB = (int)($entry['VariableB'] ?? 0);
            $direction = (int)($entry['SyncDirection'] ?? 0);
            $useAction = (bool)($entry['UseRequestAction'] ?? true);

            if ($varA <= 0 || $varB <= 0 || $varA === $varB) {
                continue;
            }

            // Prüfen, ob Variable A der Auslöser war und nach B synchronisiert werden darf
            if ($SenderID === $varA && ($direction === 0 || $direction === 1)) {
                $this->SyncValue($varA, $varB, $newValue, $useAction);
            }

            // Prüfen, ob Variable B der Auslöser war und nach A synchronisiert werden darf
            if ($SenderID === $varB && ($direction === 0 || $direction === 2)) {
                $this->SyncValue($varB, $varA, $newValue, $useAction);
            }
        }
    }

    public function SyncAll()
    {
        $syncList = json_decode($this->ReadPropertyString('SyncList'), true);
        if (!is_array($syncList)) {
            echo "Keine Einträge vorhanden.";
            return;
        }

        $count = 0;
        foreach ($syncList as $entry) {
            if (empty($entry['Active'])) {
                continue;
            }

            $varA = (int)($entry['VariableA'] ?? 0);
            $varB = (int)($entry['VariableB'] ?? 0);
            $direction = (int)($entry['SyncDirection'] ?? 0);
            $useAction = (bool)($entry['UseRequestAction'] ?? true);

            if (!IPS_VariableExists($varA) || !IPS_VariableExists($varB) || $varA === $varB) {
                continue;
            }

            // Bei Bidirektional (0) oder A -> B (1) wird A als führend angenommen
            if ($direction === 0 || $direction === 1) {
                $this->SyncValue($varA, $varB, GetValue($varA), $useAction);
                $count++;
            } elseif ($direction === 2) {
                // Bei B -> A (2) wird B als führend angenommen
                $this->SyncValue($varB, $varA, GetValue($varB), $useAction);
                $count++;
            }
        }

        echo sprintf("%d aktive Synchronisations-Paare wurden abgeglichen.", $count);
    }

    public function SyncPair(int $sourceID, int $targetID, bool $useAction = true)
    {
        if (!IPS_VariableExists($sourceID) || !IPS_VariableExists($targetID)) {
            return;
        }
        $this->SyncValue($sourceID, $targetID, GetValue($sourceID), $useAction);
    }

    private function SyncValue(int $sourceID, int $targetID, $value, bool $useAction): void
    {
        if (!IPS_VariableExists($targetID)) {
            return;
        }

        // Semaphore pro Zielvariable verhindert Race-Conditions bei schnellen Hardware-Rückmeldungen
        $semaphoreName = 'VSYNC_' . $this->InstanceID . '_TARGET_' . $targetID;
        if (!IPS_SemaphoreEnter($semaphoreName, 1000)) {
            $this->SendDebug('Sync Blocked', 'Semaphore für Ziel ' . $targetID . ' aktiv, überspringe Update.', 0);
            return;
        }

        try {
            $targetVar = IPS_GetVariable($targetID);
            $currentTargetValue = GetValue($targetID);

            // Typkonvertierung passend zur Zielvariable
            $convertedValue = $this->ConvertValue($value, $targetVar['VariableType']);

            // Endlosschleifen-Schutz: Nur senden, wenn der Zielwert tatsächlich abweicht
            if ($this->ValuesAreEqual($currentTargetValue, $convertedValue, $targetVar['VariableType'])) {
                $this->SendDebug('Sync Skipped', 'Ziel (' . $targetID . ') hat bereits den Wert ' . json_encode($convertedValue), 0);
                return;
            }

            // Prüfen, ob die Variable zu einer Instanz gehört (Standardaktion) oder ein eigenes Aktionsskript besitzt
            if ($useAction && $this->HasAction($targetVar)) {
                $this->SendDebug('RequestAction', 'Quelle ' . $sourceID . ' -> Ziel ' . $targetID . ' | Wert: ' . json_encode($convertedValue), 0);
                RequestAction($targetID, $convertedValue);
            } else {
                $this->SendDebug('SetValue', 'Quelle ' . $sourceID . ' -> Ziel ' . $targetID . ' | Wert: ' . json_encode($convertedValue), 0);
                SetValue($targetID, $convertedValue);
            }
        } catch (Exception $e) {
            $this->SendDebug('Error', $e->getMessage(), 0);
        } finally {
            IPS_SemaphoreLeave($semaphoreName);
        }
    }

    private function HasAction(array $varInfo): bool
    {
        $actionID = ($varInfo['VariableCustomAction'] > 0) ? $varInfo['VariableCustomAction'] : $varInfo['VariableAction'];
        return $actionID > 10000 && IPS_ObjectExists($actionID);
    }

    private function ConvertValue($value, int $targetType)
    {
        switch ($targetType) {
            case VARIABLETYPE_BOOLEAN:
                return (bool)$value;
            case VARIABLETYPE_INTEGER:
                return (int)round((float)$value);
            case VARIABLETYPE_FLOAT:
                return (float)$value;
            case VARIABLETYPE_STRING:
                return (string)$value;
            default:
                return $value;
        }
    }

    private function ValuesAreEqual($val1, $val2, int $type): bool
    {
        if ($type === VARIABLETYPE_FLOAT) {
            return abs((float)$val1 - (float)$val2) < 0.0001;
        }
        return $val1 === $val2;
    }
}
