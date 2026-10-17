# VarSync – Variable Synchronizer

IP-Symcon-Modul zur Synchronisation von Variablen.

## Ausgangsstand

Dieses Repository startet mit dem Modulstand aus `VarSync-v3.zip` (Bibliotheksversion 1.2, Build 3). Die Moduldateien liegen unter `VarSync/`.

## Funktionen

- Bidirektionale Synchronisation (A ↔ B)
- Unidirektionale Synchronisation (A → B oder B → A)
- Wahlweise Ausführung über `RequestAction()`
- Automatische Synchronisation bei Wertänderungen
- Manueller Abgleich aktiver Synchronisationspaare
- Typkonvertierung und Schutz vor unnötigen Updates

## Installation

Das Verzeichnis `VarSync` in die gewünschte IP-Symcon-Modulquelle übernehmen und das Modul in IP-Symcon installieren.

## Entwicklungsworkflow

- `main`: stabiler, freigegebener Stand
- `develop`: laufende Entwicklung und Tests
- Änderungen zuerst auf `develop` umsetzen und auf einem IP-Symcon-Testsystem prüfen.
- Freigegebene Änderungen per Pull Request von `develop` nach `main` übernehmen.

## Versionshinweis

Die ursprünglichen Moduldateien werden als Startpunkt erhalten. Änderungen am Modulcode und an den Metadaten werden in nachfolgenden Commits nachvollziehbar dokumentiert.
