/*************************************************************************************
 *
 * 	This script is to demonstrate how to accept a keyword query and return the text
 * 		of the verse as JSON, for use by a JavaScript front end.
 * 		NOTE: We are only doing a verse reference here. Using a word as the keyword
 * 		to find verses containing that word is scripts 6 and 7.
 *
 * 	Run it on its own:   node "5. AJAX Response.js"
 * 	                     then open localhost:3000/?keyword=Eph+2:8-10
 * 	Or run them all:     node server.js
 * 	                     then open localhost:3000/5?keyword=Eph+2:8-10
 *
 * 	This is the one script whose output is not a web page. The client-side demos in
 * 	the /js folder are written against the PHP version of this endpoint; the JSON
 * 	shape here is identical, so they work against either.
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const {
	dbConfig, getBookByKeyword, putNumRefInArray, expandVerseList, stripStrongs
} = require('./bible.js');

async function lookup(keyword) {

	const db = await mysql.createConnection(dbConfig);

	try {

		const BookData = await getBookByKeyword(db, keyword);

		const bid  = BookData.bid;
		const book = BookData.book;

		const { numKeys } = await putNumRefInArray(db, BookData);

		const Out = [];
		let corrected_ref = '';
		let old_chapter = 0;
		let chapters = 1;

		for (const numKey of numKeys) {

			const chapter = numKey.chapter;

			if (old_chapter && chapter !== old_chapter) {
				chapters++;
			}
			old_chapter = chapter;

			const verses = numKey.verses;
			corrected_ref += `${book} ${chapter}:${verses}; `;

			for (const v of expandVerseList(verses)) {

				const [rows] = await db.query(
					'SELECT * FROM `kjvs` ' +
					'WHERE `book` = ? AND `chapter` = ? AND `verse` = ? ' +
					'LIMIT 1',
					[bid, chapter, v]
				);

				if (!rows.length) { continue; }

				Out.push({
					book:    book,
					chapter: chapter,
					verse:   rows[0].verse,
					text:    stripStrongs(rows[0].text)
				});
			}
		}

		corrected_ref = corrected_ref.replace(/; $/, '');

		/*******************************
		 *
		 * Build the return object.
		 *
		 * A single verse returns its text plainly. Anything longer wraps each verse
		 * in <verse><num> tags so the front end can style the verse numbers.
		 *
		 *****************************/

		const Return = { ref: corrected_ref, text: [] };

		if (chapters === 1 && Out.length === 1) {
			Return.text[0] = Out[0].text;
		} else {
			Return.text = Out.map(v => `<verse><num>${v.verse}</num> ${v.text}</verse>`);
		}

		return Return;

	} finally {
		await db.end();
	}
}

async function handleRequest(req, res) {

	// PHP reads $_GET['keyword']. In Node the query string is ours to parse —
	// URL needs an absolute address, so we supply a throwaway base.
	const url = new URL(req.url, 'http://localhost');
	const keyword = url.searchParams.get('keyword') || 'John 3:16';

	try {
		const Return = await lookup(keyword);
		res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
		res.end(JSON.stringify(Return));
	} catch (err) {
		// Errors return JSON too, so the front end has one shape to handle.
		res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
		res.end(JSON.stringify({ error: err.message }));
	}
}

module.exports = { handleRequest, lookup };

if (require.main === module) {
	http.createServer((req, res) => {
		if (req.url === '/favicon.ico') { res.writeHead(204); return res.end(); }
		handleRequest(req, res);
	}).listen(3000, () => {
		console.log('Listening on http://localhost:3000  (Ctrl+C to stop)');
		console.log('Try http://localhost:3000/?keyword=Eph+2:8-10');
	});
}
