/*************************************************************************************
 *
 * 	This script is to demonstrate the initial database connection (assuming MySQL)
 * 		and loading of a single verse.
 *
 * 	Run it on its own:   node "1. Load a Single Verse.js"    then open localhost:3000
 * 	Or run them all:     node server.js                      then open localhost:3000
 *
 * 	Node note: unlike the PHP and Python versions, this file IS the web server.
 * 	PHP and CGI Python start a fresh process for every request and print to it.
 * 	Node starts once and stays running, handling every request in the same
 * 	process. That difference drives most of what looks unfamiliar below.
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const { backLink } = require('./bible.js');

/*******************************
 *
 * Connect to the database.
 * For demonstration purposes I actually used db_user and db_password for my local intranet.
 * 	You'll want to use something more secure.
 * The database is named `osbible` (Yours may be different.)
 *
 * Because the process is long-lived, we open the connection once per request
 * here for clarity. In production you would use a connection pool
 * (mysql.createPool) so concurrent requests don't queue behind each other.
 *
 *****************************/

const dbConfig = {
	host:     'localhost',
	user:     'db_user',
	password: 'db_password',
	database: 'osbible',
	charset:  'utf8mb4'
};

async function loadVerse(reference) {

	// db will be our connection object. mysql2 requires it for almost everything.
	const db = await mysql.createConnection(dbConfig);

	try {

		/******************************
		 *
		 * Get the list of book names from the database and put them into lookups.
		 *
		 * PHP stored the whole row in $Books[$id]. JavaScript objects could do the
		 * same, but a Map is the better fit for a lookup keyed by data rather than
		 * by fixed property names. (I use capital letters to note these lookups.
		 * It helps me identify them more easily within the coding.)
		 *
		 *****************************/

		// The mysql query. Destructuring pulls the rows out of [rows, fields].
		const [bookRows] = await db.query('SELECT * FROM `kjv_books`');

		const Books = new Map();	// book id   -> whole row
		const Bids  = new Map();	// book name -> book id

		for (const row of bookRows) {
			Books.set(row.id, row);
			Bids.set(row.book, row.id);
		}

		/*******************************
		 *
		 * Get a single verse based on a simple reference.
		 *
		 *****************************/

		// Since we're assuming a correct reference, we split the reference.
		// Note: like the PHP original, this takes only the first two pieces, so a
		// numbered book ("1 John 3:16") would break here. Script 3 fixes that.
		const [book, ref] = reference.split(' ');

		// Using the Bids (book ids) lookup we can get the book id.
		const bid = Bids.get(book);

		// We split the remaining ref into chapter and verse.
		const [chapter, verse] = ref.split(':');

		/********************************************
		 *
		 * Explanation of the query...
		 * 	The ? placeholders are filled in by the driver, which escapes each value
		 * 	properly. This replaces PHP's sprintf + mysqli_real_escape_string, and
		 * 	unlike that pairing it is safe against injection by construction rather
		 * 	than by remembering to escape.
		 * 	We limit it to one so we don't have to run through the entire table after
		 * 	we've found the data we need.
		 */

		const [verseRows] = await db.query(
			'SELECT `text` FROM `kjvs` ' +
			'WHERE `book` = ? AND `chapter` = ? AND `verse` = ? ' +
			'LIMIT 1',
			[bid, chapter, verse]
		);

		if (verseRows.length === 0) {
			return 'Verse not found.';
		}

		// Assign the data to text
		let text = verseRows[0].text;

		// Filter out the Strong's numbers (we don't need them now)
		text = text.replace(/\{.*?\}/g, '');

		// Build the verse
		return `&ldquo;${text}&rdquo;&mdash;${book} ${chapter}:${verse}`;

	} finally {
		// Always close, even if something above threw.
		await db.end();
	}
}

/*******************************
 *
 * The request handler.
 *
 * This is the part PHP and CGI Python get for free from Apache. Keeping it in
 * its own function (rather than inline in createServer) is what lets server.js
 * mount this script as one route among several.
 *
 *****************************/

async function handleRequest(req, res) {

	try {

		// We will start with a simple reference that won't need correcting,
		// 	but will need the book name converted to the id of the book
		const output = await loadVerse('John 3:16');

		res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(backLink() + output);

	} catch (err) {
		res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(`<p>Error: ${err.message}</p>`);
	}
}

// Make the handler available to server.js.
module.exports = { handleRequest, loadVerse };

/*******************************
 *
 * Start a server ONLY if this file was run directly.
 *
 * require.main is the file Node was launched with. When you run this script
 * yourself it equals this module, so the server starts. When server.js requires
 * it instead, it does not — which is what stops eight lesson files from all
 * trying to claim port 3000 at once.
 *
 *****************************/

if (require.main === module) {

	const server = http.createServer((req, res) => {
		// Browsers ask for this on every page load. Answer it and move on, or
		// the verse query runs twice for every visit.
		if (req.url === '/favicon.ico') {
			res.writeHead(204);
			return res.end();
		}
		handleRequest(req, res);
	});

	server.listen(3000, () => {
		console.log('Listening on http://localhost:3000  (Ctrl+C to stop)');
	});
}

/**********************************************************
 *  The page output should look like...
 * 	&ldquo;For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.&rdquo;&mdash;John 3:16
 *
 *********************************************************/
