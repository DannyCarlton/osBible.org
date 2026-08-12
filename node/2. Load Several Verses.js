/*************************************************************************************
 *
 * 	This script is to demonstrate the initial database connection (assuming MySQL)
 * 		and loading of several verses.
 *
 * 	Run it on its own:   node "2. Load Several Verses.js"    then open localhost:3000
 * 	Or run them all:     node server.js                      then open localhost:3000/2
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const { dbConfig, backLink, stripStrongs } = require('./bible.js');

async function loadPassages() {

	const db = await mysql.createConnection(dbConfig);

	try {

		/******************************
		 *
		 * Get the list of book names from the database and put them into a lookup.
		 *
		 *****************************/

		const [bookRows] = await db.query('SELECT * FROM `kjv_books`');

		const Bids = new Map();		// book name -> book id
		for (const row of bookRows) {
			Bids.set(row.book, row.id);
		}

		/*******************************
		 *
		 * Get a selection of verses based on a simple reference.
		 *
		 *****************************/

		// We will start with a few typical passage references.
		// 	The first is the standard two verse, comma delimited reference.
		//	The second is the dash delimited verse list.
		const reference1 = 'John 3:16,17';
		const reference2 = 'John 3:1-16';

		// Since we're assuming a correct reference, we split the reference
		const [book1, ref1] = reference1.split(' ');
		const [book2, ref2] = reference2.split(' ');

		// Using the Bids (book ids) lookup we can get the book id.
		const bid1 = Bids.get(book1);
		const bid2 = Bids.get(book2);

		// We split the remaining ref into chapter and verse.
		const [chapter1, verses1] = ref1.split(':');
		const [chapter2, verses2] = ref2.split(':');

		/********************************************
		 *
		 *  Let's start with reference #1
		 * 		Since we have two numbers separated by a comma, we can fetch the two
		 * 		and retrieve those specific verses.
		 *
		 * 	We allow an OR statement to get the two verses, and limit the results
		 * 	to just 2.
		 *
		 *******************************************/

		const [first, second] = verses1.split(',');

		const [rows1] = await db.query(
			'SELECT * FROM `kjvs` ' +
			'WHERE `book` = ? AND `chapter` = ? ' +
			'AND (`verse` = ? OR `verse` = ?) ' +
			'LIMIT 2',
			[bid1, chapter1, first, second]
		);

		const Verses1 = new Map();
		for (const row of rows1) {
			// Filter out the Strong's numbers (we don't need them now)
			Verses1.set(row.verse, stripStrongs(row.text));
		}

		/********************************************
		 *
		 *  Now let's do reference #2
		 * 		Since we have the beginning and ending of a list, we simply use
		 * 		those as boundaries.
		 *
		 *******************************************/

		const [start, end] = verses2.split('-');

		const [rows2] = await db.query(
			'SELECT * FROM `kjvs` ' +
			'WHERE `book` = ? AND `chapter` = ? ' +
			'AND `verse` >= ? AND `verse` <= ?',
			[bid2, chapter2, start, end]
		);

		const Verses2 = new Map();
		for (const row of rows2) {
			Verses2.set(row.verse, stripStrongs(row.text));
		}

		/*******************************
		 *
		 * Build the output.
		 *
		 *****************************/

		const passage = (heading, Verses, book, chapter, verses) => {
			let out = '';
			for (const [v, text] of Verses) {
				out += `<p style="text-align:justify;margin:0"><b>${v}</b> ${text}</p>`;
			}
			return `<div style="width:400px;margin-left:50px">` +
			       `<h3>${heading}</h3>${out}` +
			       `<p style="text-align:right">&mdash;${book} ${chapter}:${verses}</p></div>`;
		};

		return passage('Passage #1', Verses1, book1, chapter1, verses1) +
		       passage('Passage #2', Verses2, book2, chapter2, verses2);

	} finally {
		await db.end();
	}
}

async function handleRequest(req, res) {
	try {
		const output = await loadPassages();
		res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(backLink() + output);
	} catch (err) {
		res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(`<p>Error: ${err.message}</p>`);
	}
}

module.exports = { handleRequest, loadPassages };

// Start a server ONLY if this file was run directly. See script 1 for why.
if (require.main === module) {
	http.createServer((req, res) => {
		if (req.url === '/favicon.ico') { res.writeHead(204); return res.end(); }
		handleRequest(req, res);
	}).listen(3000, () => {
		console.log('Listening on http://localhost:3000  (Ctrl+C to stop)');
	});
}
