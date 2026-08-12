/*************************************************************************************
 *
 * 	This script is to demonstrate a more complicated search using multiple keywords,
 * 		including quoted phrases and wildcards.
 *
 * 	Run it on its own:   node "7. Find Verses Containing Multiple Key Words.js"
 * 	Or run them all:     node server.js      then open localhost:3000/7
 *
 * 	Note the table changes here. Because of the Strong's coding in `kjvs` that table
 * 	is not usable for a complicated search — the braces sit between words and break
 * 	phrase matching. So we use `kjv`, which holds the plain text and carries the book
 * 	name as a string, meaning no book lookup is needed.
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const { dbConfig, backLink, parseKeywords, stripStrongs, escapeRegExp } = require('./bible.js');

const Examples = [
	{ keyword: 'with love',        note: 'Two keywords.' },
	{ keyword: '"loved her"',      note: 'A two word phrase.' },
	{ keyword: 'David "loved her"', note: 'A word and a phrase.' },
	{ keyword: 'love*',            note: 'Keyword with wild card.' }
];

/*******************************
 *
 * highlight — make each search term bold and dark red within a verse.
 *
 * A wildcard term needs its * turned into a regex fragment here too, or "love*"
 * would highlight nothing: the SQL matched "lovingkindness" but the highlighter
 * would be looking for a literal asterisk.
 *
 *****************************/

function highlight(text, terms) {
	for (const term of terms) {
		// Escape everything except the asterisk, then give the asterisk meaning.
		const pattern = escapeRegExp(term).replace(/\\\*/g, '[a-zA-Z]*');
		text = text.replace(
			new RegExp(`\\b${pattern}\\b`, 'gi'),
			'<b style="color:#990000">$&</b>'
		);
	}
	return text;
}

async function runExamples() {

	const db = await mysql.createConnection(dbConfig);

	try {
		let out = '<h1>Find verses containing multiple key words.</h1>';

		for (let i = 0; i < Examples.length; i++) {

			const { keyword, note } = Examples[i];

			// Build the WHERE clause and get back the plain terms for highlighting
			const { searchKey, terms } = parseKeywords(keyword);

			// searchKey is a fragment of SQL rather than a value, so it is
			// interpolated rather than passed as a ? parameter. See sqlQuote in
			// bible.js for why, and for what that costs.
			const [rows] = await db.query(
				`SELECT * FROM \`kjv\` WHERE ${searchKey} LIMIT 8`
			);

			out += `<div style="float:left;width:380px;margin-left:30px;padding:15px;` +
			       `margin-bottom:10px;border:1px solid #000">`;
			out += `<div style="width:380px;margin-bottom:20px;">` +
			       `<h3>Example #${i + 1}. ${note}` +
			       `<small style="font-weight:normal"><br>keyword(s): ${keyword}</small></h3></div>`;

			for (const row of rows) {
				// `kjv` carries the book name directly, so no lookup is needed
				const ref  = `${row.book} ${row.chapter}:${row.verse}`;
				const text = highlight(stripStrongs(row.text), terms);

				out += `<div style="width:380px;margin-bottom:20px">` +
				       `&ldquo;${text}&rdquo;&mdash;${ref}</div>`;
			}

			out += '</div>\n';
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
