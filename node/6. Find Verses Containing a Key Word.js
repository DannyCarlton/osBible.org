/*************************************************************************************
 *
 * 	This script is to demonstrate a simple search for verses containing a single keyword.
 *
 * 	Run it on its own:   node "6. Find Verses Containing a Key Word.js"
 * 	Or run them all:     node server.js      then open localhost:3000/6
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const { dbConfig, backLink, wordBoundary, getBooks, stripStrongs, escapeRegExp } = require('./bible.js');

async function findVerses() {

	const db = await mysql.createConnection(dbConfig);

	try {

		// We will start with a single word
		const keyword = 'love';

		// The book names, so we can turn the numeric `book` column into a name
		const { bookName } = await getBooks(db);

		/********************************************
		 *
		 *  After a lot of trial and error I have found that a word-boundary regular
		 * 		expression is the best way to pull verses based on a single word out
		 * 		of the database. Without the boundaries, "love" also matches "gloves"
		 * 		and "beloved".
		 *
		 * 	The exact syntax depends on the server: MariaDB and MySQL 5.7 use
		 * 	[[:<:]] and [[:>:]], MySQL 8 uses \b. See the top of bible.js if this
		 * 	throws a regex error.
		 *
		 * 	We'll only get the first ten this time. Script 8 does the full list
		 * 	with pagination.
		 *
		 *******************************************/

		const [rows] = await db.query(
			'SELECT * FROM `kjvs` WHERE `text` REGEXP ? LIMIT 10',
			[wordBoundary(keyword)]
		);

		// Put the text and the reference into a list.
		// Yes, we could output it here, but more often than not there will be other
		// things we need to do, or we'd put this code in a function that returns
		// only this list.
		const Verses = rows.map(row => ({
			ref:  `${bookName.get(row.book)} ${row.chapter}:${row.verse}`,
			text: stripStrongs(row.text)
		}));

		/*******************************
		 *
		 * Now we'll output the results. First a reminder of the keyword...
		 *
		 *****************************/

		let out = '<h1>Find verses containing a key word.</h1>';
		out += `<div style="width:400px;margin-left:50px;margin-bottom:20px">` +
		       `<h3>keyword: &ldquo;${keyword}&rdquo;</h3></div>`;

		// Here we use a regular expression to find the keyword, make it bold and
		// dark red. \b is JavaScript's word boundary — the same idea as the SQL
		// above, different syntax. The g flag replaces every occurrence, which is
		// PHP's preg_replace default but not JavaScript's.
		const highlight = new RegExp(`\\b${escapeRegExp(keyword)}\\b`, 'gi');

		for (const Verse of Verses) {
			const text = Verse.text.replace(highlight, '<b style="color:#990000">$&</b>');
			out += `<div style="width:400px;margin-left:50px;margin-bottom:20px">` +
			       `&ldquo;${text}&rdquo;&mdash;${Verse.ref}</div>`;
		}

		return out;

	} finally {
		await db.end();
	}
}

async function handleRequest(req, res) {
	try {
		const output = await findVerses();
		res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(backLink() + output);
	} catch (err) {
		res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(`<p>Error: ${err.message}</p>`);
	}
}

module.exports = { handleRequest, findVerses };

if (require.main === module) {
	http.createServer((req, res) => {
		if (req.url === '/favicon.ico') { res.writeHead(204); return res.end(); }
		handleRequest(req, res);
	}).listen(3000, () => {
		console.log('Listening on http://localhost:3000  (Ctrl+C to stop)');
	});
}
