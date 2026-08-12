/*************************************************************************************
 *
 * 	This script is to demonstrate how to catch a variety of numeric (chapter and verse)
 * 		reference types including multiple verses, multiple chapters, lists and others.
 *	It uses getBookByKeyword to resolve the book, and putNumRefInArray to turn the
 *		numeric part of the reference into a list of chapter/verse pairs.
 *
 * 	Run it on its own:   node "4. Catch Various Number Reference Types.js"
 * 	Or run them all:     node server.js      then open localhost:3000/4
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const {
	dbConfig, backLink, getBookByKeyword, putNumRefInArray, expandVerseList, stripStrongs
} = require('./bible.js');

// We will start with a few typical passage references.
const Examples = [
	{ reference: '3 John 3',                 note: 'A book with one chapter that the reference targets the verse' },
	{ reference: 'John 3',                   note: 'A book with multiple chapters referencing the entire chapter' },
	{ reference: 'Eph 2:8,9,12',             note: 'Verse not in sequence' },
	{ reference: 'Eph 2:8-10,12',            note: 'List with additional verse' },
	{ reference: 'Luke 7:12; 8:42; 9:38',    note: 'Verses across several chapters' },
	{ reference: 'Luke 7:12; 8:42; 9:38;',   note: 'Verses across several chapters (but ending with a stray semi-colon)' },
	{ reference: 'John 3.16',                note: 'Using a period instead of a colon' }
];

async function runExamples() {

	const db = await mysql.createConnection(dbConfig);

	try {
		let out = '';

		for (const example of Examples) {

			// create a visual divider between references and results
			out += '<hr>';

			// To avoid confusing the filter we'll make all book references lower case
			const reference = example.reference.toLowerCase();

			// Split the book part from the number part
			const BookData = await getBookByKeyword(db, reference);

			out += `<b>reference: ${reference}</b> (${example.note})<br>`;

			const bid  = BookData.bid;
			const book = BookData.book;

			// Sending the whole BookData object gives putNumRefInArray everything
			// it needs to process the number keys
			const { numKeys } = await putNumRefInArray(db, BookData);

			// initialize/reset some variables
			const Out = [];
			let corrected_ref = '';
			let old_chapter = 0;
			let chapters = 1;

			// run through the number keys
			for (const numKey of numKeys) {

				const chapter = numKey.chapter;

				// if it's different from the previous chapter, count the chapters
				if (old_chapter && chapter !== old_chapter) {
					chapters++;
				}
				old_chapter = chapter;

				const verses = numKey.verses;
				corrected_ref += `${book} ${chapter}:${verses}; `;

				// Work out whether it's one verse, verses divided by commas,
				// a range with a dash, or in some cases both.
				const verseList = expandVerseList(verses);

				for (const v of verseList) {

					const [rows] = await db.query(
						'SELECT * FROM `kjvs` ' +
						'WHERE `book` = ? AND `chapter` = ? AND `verse` = ? ' +
						'LIMIT 1',
						[bid, chapter, v]
					);

					if (!rows.length) { continue; }

					// Filter out the Strong's numbers (we don't need them now)
					Out.push({
						book:    book,
						chapter: chapter,
						verse:   rows[0].verse,
						text:    stripStrongs(rows[0].text)
					});
				}
			}

			// Trim the trailing "; " the loop leaves behind
			corrected_ref = corrected_ref.replace(/; $/, '');

			// Show the corrected reference with the verse or verses.
			// One verse in one chapter reads best as a plain quotation;
			// several verses want their numbers; several chapters want both.
			if (chapters === 1 && Out.length === 1) {
				out += `<b>${corrected_ref}</b><br>&ldquo;${Out[0].text}&rdquo;`;
			} else if (chapters === 1) {
				out += `<b>${corrected_ref}</b><br>`;
				for (const Verse of Out) {
					out += `<b>${Verse.verse}</b> ${Verse.text}<br>`;
				}
			} else {
				out += `<b>${corrected_ref}</b><br>`;
				for (const Verse of Out) {
					out += `<b>${Verse.chapter}:${Verse.verse}</b> ${Verse.text}<br>`;
				}
			}
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
