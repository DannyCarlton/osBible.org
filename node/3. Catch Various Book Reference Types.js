/*************************************************************************************
 *
 * 	This script is to demonstrate how to catch a variety of reference types including
 * 		abbreviations and misspellings.
 *	It uses the function getBookByKeyword which takes what is offered as the
 *		book, whether it's the full name, an abbreviation or a misspelling, and
 *		returns the data held in the kjv_books table for that book.
 *
 * 	Run it on its own:   node "3. Catch Various Book Reference Types.js"
 * 	Or run them all:     node server.js      then open localhost:3000/3
 *
 * 	Note: getBookByKeyword lives in bible.js rather than being repeated in this
 * 	file the way the PHP version repeats it. Scripts 4 and 5 use the same function.
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const { dbConfig, backLink, getBookByKeyword, stripStrongs } = require('./bible.js');

// We will start with a few typical passage references.
// 	We put them in a list because we use several of them to demonstrate the
// 	various forms a reference can take.
const Examples = [
	{ reference: 'Jn 3:16',          note: 'A typical abbreviation' },
	{ reference: '2 pt 1:1',         note: 'Several examples of how books with numbers are entered. Just a number' },
	{ reference: '2nd pt 1:1',       note: '...Number with ordinal' },
	{ reference: 'ii pt 1:1',        note: '...Roman numeral' },
	{ reference: 'Second Peter 1:1', note: '...Ordinal word' },
	{ reference: 'gnesis 1:1',       note: 'Common misspelling' }
];

async function runExamples() {

	const db = await mysql.createConnection(dbConfig);

	try {
		let out = '';

		for (const example of Examples) {

			// We'll keep the original reference to display
			const _reference = example.reference;

			// To avoid confusing the filter we'll make all book references lower case
			const reference = example.reference.toLowerCase();

			// Split the book part from the number part and return it all in an object
			const BookData = await getBookByKeyword(db, reference);

			// We want the book id for the next database query
			const bid  = BookData.bid;
			// Here we'll have the book name
			const book = BookData.book;

			// We'll split the chapter from the verse
			const [chapter, verse] = BookData.num_key.split(':');

			// Now we'll build our query...
			const [rows] = await db.query(
				'SELECT `text` FROM `kjvs` ' +
				'WHERE `book` = ? AND `chapter` = ? AND `verse` = ? ' +
				'LIMIT 1',
				[bid, chapter, verse]
			);

			// Filter out the Strong's numbers (we don't need them now)
			const text = rows.length ? stripStrongs(rows[0].text) : '(verse not found)';

			// Show the original reference with the verse and the corrected reference
			out += `Original reference: ${_reference} (${example.note})<br>` +
			       `Result: &ldquo;${text}&rdquo;&mdash;${book} ${chapter}:${verse}<hr>`;
		}

		return out;

	} finally {
		await db.end();
	}
}

async function handleRequest(req, res) {
	try {
		const output = await runExamples();
		res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(backLink() + output);
	} catch (err) {
		res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(`<p>Error: ${err.message}</p>`);
	}
}

module.exports = { handleRequest, runExamples };

if (require.main === module) {
	http.createServer((req, res) => {
		if (req.url === '/favicon.ico') { res.writeHead(204); return res.end(); }
		handleRequest(req, res);
	}).listen(3000, () => {
		console.log('Listening on http://localhost:3000  (Ctrl+C to stop)');
	});
}
