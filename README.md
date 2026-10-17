# VarSync – Variable Synchronizer

IP-Symcon-Modul zur Synchronisation von Variablen.

## Ausgangsstand

Dieses Repository startet mit dem Modulstand aus `VarSync-v3.zip` (Bibliotheksversion 1.2, Build 3). Die `library.json` liegt im Repository-Stammverzeichnis; die Moduldateien liegen im Verzeichnis `VarSyncModule/`.

## Funktionen

- Bidirektionale Synchronisation (A ↔ B)
- Unidirektionale Synchronisation (A → B oder B → A)
- Wahlweise Ausführung über `RequestAction()`
- Automatische Synchronisation bei Wertänderungen
- Manueller Abgleich aktiver Synchronisationspaare
- Typkonvertierung und Schutz vor unnötigen Updates

## Installation

Das Repository in die gewünschte IP-Symcon-Modulquelle einbinden. Die `library.json` liegt im Stammverzeichnis; `VarSyncModule/` enthält die Moduldefinition, das Formular und den PHP-Code.

## Entwicklungsworkflow

- `main`: stabiler, freigegebener Stand
- `develop`: laufende Entwicklung und Tests
- Änderungen zuerst auf `develop` umsetzen und auf einem IP-Symcon-Testsystem prüfen.
- Freigegebene Änderungen per Pull Request von `develop` nach `main` übernehmen.

## Versionshinweis

Die ursprünglichen Moduldateien werden als Startpunkt erhalten. Änderungen am Modulcode und an den Metadaten werden in nachfolgenden Commits nachvollziehbar dokumentiert.
