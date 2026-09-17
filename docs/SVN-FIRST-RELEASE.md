# Prima pubblicazione su WordPress.org

Questa guida pubblica la release `2.1.1` del plugin `sernicola-labs-ai-friendly`.
Non inserire mai la password SVN in un file, nel terminale condiviso o nel repository.

## Prima del commit

1. Imposta la password SVN nel profilo WordPress.org e attendi l'attivazione dei permessi.
2. Esegui il controllo finale nel Playground indicato dalla revisione.
3. Verifica che `sernicola-labs-ai-friendly.php` riporti `Version: 2.1.1` e che
   `readme.txt` riporti `Stable tag: 2.1.1`.
4. Valida il readme con il validatore ufficiale: https://wordpress.org/plugins/developers/readme-validator/

## Checkout e preparazione

In PowerShell, da questa directory, esegui:

```powershell
svn checkout https://plugins.svn.wordpress.org/sernicola-labs-ai-friendly svn-checkout
.\tools\prepare-svn-release.ps1 -SvnCheckoutPath .\svn-checkout
```

Il primo comando potrebbe chiedere utente e password SVN. L'utente è `slabsit` (rispettando maiuscole/minuscole). Lo script prepara solo la copia locale: non effettua commit.

Controlla quindi le modifiche:

```powershell
Set-Location .\svn-checkout
svn status
svn diff --summarize
```

## Pubblicazione

Quando lo stato locale è corretto, invia il commit:

```powershell
svn commit -m "Release 2.1.1" --username slabsit
```

Il client chiederà la password SVN in modo interattivo. In un checkout nuovo, lo script può anche preparare e pubblicare tutto in un solo passaggio usando l'opzione `-Commit`. Non caricare mai lo ZIP nel repository SVN.

## Asset della pagina del plugin (facoltativi)

Gli asset devono stare nella directory SVN superiore `assets/`, non in `trunk/assets/`.
I nomi utili sono `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png` e, opzionalmente, `banner-1544x500.png`.

Dopo averli copiati in `assets`, esegui `svn add assets`, verifica `svn status` e fai un commit separato con un messaggio descrittivo.
