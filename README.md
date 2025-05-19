# Card Collector Center - CCC
# WebApp per la compravendita di carte collezionabili
Benvenuti su **CardHub**, un'applicazione web pensata per la compravendita di carte collezionabili, ispirata a piattaforme come *Card Market*.  
Il progetto è incentrato sulla realizzazione di un'infrastruttura completa, sicura e intuitiva per venditori e collezionisti.

## 🎯 Obbiettivi principali
- Consentire agli utenti di **acquistare e vendere carte** in modo semplice e sicuro.
- Fornire una **gestione completa degli annunci**, profili, wishlist, ordini e spedizioni.
- Offrire **strumenti avanzati per gli amministratori** per la moderazione e il controllo della piattaforma.

## 🗃️ Caratteristiche principali
- 🔍 **Motore di ricerca carte** con filtri per edizione, rarità, condizione e prezzo  
- 📋 **Scheda dettagliata** per ogni carta: info tecniche, immagini, prezzi medi
- 🛒 **Gestione carrello, wishlist e ordini**
- 💳 **Supporto a più metodi di pagamento** (es. PayPal, contrassegno)
- 🌟 **Sistema di valutazione dei venditori**
- 📊 **Dashboard amministrativa** per monitoraggio, gestione contenuti e reportistica


## 🧱 Tecnologie utilizzate

- **Frontend**: HTML/CSS/JS
- **Backend**: PHP
- **Database**: MySQL
- **Tools**: DBMain per progettazione concettuale e logica

## 👩‍💼 Ruoli supportati
- **Utente registrato**: può creare annunci, gestire wishlist, acquistare carte  
- **Amministratore**: modera utenti e annunci, genera report e statistiche

## 📫 Contatti
Se avete domande o feedback non contattateci, siamo degli antipatici, soprattutto Simone.
---
## 📝 TODO:
- "# DB_Solo" (?)
- mettere le immagini per la prima pagina
- la funzione per aggiungere le carte
---
# Come far andare il sito
## 1. Installa XAMPP
Scarica e installa [XAMPP](https://www.apachefriends.org/index.html) per il tuo sistema operativo.
## 2. Scarica il progetto (quello qui sopra :D)
## 3. Riposiziona i file del progetto
Scarica questo progetto e copia l'intera cartella dentro la directory `htdocs` di XAMPP.

Di solito si trova qui:
- Windows: `C:\xampp\htdocs`
- macOS: `/Applications/XAMPP/htdocs`

## 4. Avvia Apache e MySQL
Apri il **pannello di controllo di XAMPP** e avvia:
- ✅ Apache
- ✅ MySQL
## 5. Importa il database
1. Apri `http://localhost/phpmyadmin` nel browser.
2. Crea un nuovo database, ad esempio chiamato `miodb`.
3. Vai su **Importa** e carica il file `.sql` che trovi nella cartella del progetto (es. `database.sql`).
## 5. Visita il sito
Apri il browser e vai su:
http://localhost/Database
(NOTA: La sintassi è http://localhost/nome-cartella dove `nome-cartella` è il nome della cartella del progetto)

## Precisazioni
XAMPP va aperto sempre come amministratore.
Bisogna sempre fare STOP ai processi prima di premere QUIT per chiudere, se non si fa si rompe tutto e dovrete disinstallare e reinstallare per farlo funzionare.
Ho scritto questa guida per gli impediti come me.
---
