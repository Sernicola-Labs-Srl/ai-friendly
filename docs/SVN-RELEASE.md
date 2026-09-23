# Pubblicazione su WordPress.org

Questa guida pubblica una nuova versione del plugin `sernicola-labs-ai-friendly` nel repository SVN di WordPress.org.
Vale sia per la prima pubblicazione sia per gli aggiornamenti successivi.
Non inserire mai la password SVN in un file, nel terminale condiviso o nel repository.

## Prima di preparare la release

1. Unisci su `main` la PR della release e resta su `main` aggiornato: lo script pubblica solo file tracciati da git e senza modifiche non committate.
2. Verifica che la stessa versione compaia in `Version:` e `SAIFR_VERSION` di `sernicola-labs-ai-friendly.php`, in `Stable tag:` di `readme.txt` e come voce `= x.y.z =` nel changelog del readme. Lo script si ferma se non coincidono.
3. Valida il readme con il validatore ufficiale: https://wordpress.org/plugins/developers/readme-validator/

## Preparazione

La prima volta serve il checkout (utente `slabsit`, rispettando maiuscole/minuscole):

```powershell
svn checkout https://plugins.svn.wordpress.org/sernicola-labs-ai-friendly svn-checkout
```

Poi, da questa directory:

```powershell
.\tools\prepare-svn-release.ps1 -SvnCheckoutPath .\svn-checkout
```

Lo script legge la versione dall'header del plugin (oppure usa `-Version x.y.z`) e:

- aggiorna il checkout con `svn update` e si ferma se il tag esiste già o se ci sono modifiche locali in sospeso;
- sincronizza `trunk` con i file della release: copia quelli modificati, aggiunge i nuovi e rimuove quelli non più presenti;
- crea `tags/x.y.z` come copia di `trunk` e verifica che i contenuti coincidano;
- mostra `svn status` e `svn diff --summarize`, senza inviare nulla.

Controlla l'elenco: devono comparire solo i file della release in `trunk` e il nuovo tag.

## Pubblicazione

Quando lo stato locale è corretto:

```powershell
Set-Location .\svn-checkout
svn commit -m "Release x.y.z" --username slabsit
```

Il client chiede la password SVN in modo interattivo. In alternativa lo script può preparare e pubblicare in un solo passaggio con `-Commit`.
Non caricare mai lo ZIP nel repository SVN.

Dopo il commit WordPress.org aggiorna la pagina del plugin e propone l'aggiornamento ai siti entro pochi minuti, in base allo `Stable tag` del readme in `trunk`.

Se qualcosa va storto prima del commit, `svn revert -R .` seguito dall'eliminazione della cartella `tags/x.y.z` non versionata riporta il checkout allo stato iniziale.

## Asset della pagina del plugin (facoltativi)

Gli asset devono stare nella directory SVN superiore `assets/`, non in `trunk/assets/`.
I nomi utili sono `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png` e, opzionalmente, `banner-1544x500.png`.

Dopo averli copiati in `assets`, esegui `svn add assets`, verifica `svn status` e fai un commit separato con un messaggio descrittivo.
