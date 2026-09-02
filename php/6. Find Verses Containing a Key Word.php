<?php 
echo "<h1>Find verses containing a key word.</h1>";
/*************************************************************************************
 * 
 * 	This script is to demonstrate a simple search for verses containing a single keyword.
 * 
 ***************************************************************************************/


/*******************************
 * 
 * 
 * Connect to the database. 
 * For demonstration purposes I actually used db_user and db_password for my local intranet.
 * 	You'll want to use something more secure.
 * The database is named `osbible` (Yours may be different.)
 * 
 *****************************/
# $_mysql will be our db connection variable. Mysqli requires it for almost everything.
$_mysql = mysqli_connect('localhost','db_user','db_password','osbible');


/*******************************
 * 
 * Word boundary syntax for REGEXP. READ THIS IF THE SEARCH THROWS AN ERROR.
 * 
 * This script was originally written against MariaDB 10.4, which uses the POSIX
 * 	word boundary markers [[:<:]] and [[:>:]].
 * MySQL 8.0.4 replaced the regex engine with ICU. ICU uses \b instead, and it
 * 	rejects the POSIX form outright with "repetition-operator operand invalid".
 * So the same query cannot run on both. These two variables are the switch.
 * 
 * Note the single quotes: in PHP, '\b' is a backslash followed by a b, which is
 * 	what we want. A backslash also means something inside a MySQL string literal,
 * 	so it has to reach the server doubled -- and that is exactly what
 * 	mysqli_real_escape_string() below does for us. Nothing extra to do.
 * 
 *****************************/

$WB_START='\b';			# MariaDB / MySQL 5.7:  '[[:<:]]'
$WB_END='\b';			# MariaDB / MySQL 5.7:  '[[:>:]]'


/*******************************
 * 
 * Get a selection of verses based on a simple keyword.
 * 
 *****************************/

	# We will start with a single word
$keyword='love';

# I've move the code to fetch the book names to a function (it's at the end of the script)
$bookName=getBooks();

# Always start the array empty. If the search finds nothing, the foreach at the
# 	bottom still has something to walk through instead of raising a warning.
$Verses=[];

/********************************************
 * 
 *  After a lot of trial and error I have found that a regular expression with word
 * 		boundaries around the keyword is the best way to pull verses based on a
 * 		single word out of the database. Without the boundaries, a search for "love"
 * 		would also match "glove", "beloved" and "clover".
 * 	We'll only get the first ten this time. In a later script we'll do the full list with pagination. 
 * 
 *  The whole pattern -- boundaries and keyword together -- is passed through
 * 		mysqli_real_escape_string as a single value, so the escaping is done once
 * 		and in one place.
 * 
 *******************************************/

$querytext=sprintf("SELECT	* 		FROM	`kjvs`
					WHERE	`text` 	REGEXP	'%s'
					LIMIT	10;",
			mysqli_real_escape_string($_mysql, $WB_START.$keyword.$WB_END));

	# Submit the first query
$query = mysqli_query($_mysql, $querytext);
	# Receive the results
if(mysqli_errno($_mysql))
	{
	# If there's an error, display it and the querytext for debugging
	echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
	}
# If there's a result...
# 	($query is false when the query failed, so check that first or mysqli_num_rows
# 	throws on the false.)
if($query and mysqli_num_rows($query))
	{
	# 	Begin processing the results
	while ($dbRow = mysqli_fetch_assoc($query))
		{
	# 	Get the id of this row
		$id=$dbRow['id'];
	#	get the book id
		$bid=$dbRow['book'];
	#	get the book name (from the array we created)
		$book=$bookName[$bid];
	#	get the chapter
		$chapter=$dbRow['chapter'];
	#	get the verse
		$verse=$dbRow['verse'];
	#	create the reference
		$ref="$book $chapter:$verse";
	#	get the text of the verse
		$text=$dbRow['text'];	
	#	Filter out the Strong's numbers (we don't need them now)
		$text=preg_replace('/\{(.*?)\}/', '', $text);
	#	put the text and the reference into an array.
	#	Yes, we could output it here, but more often than not there will be other things we need to do, 
	#		or put this code in a function that will only return this array
		$Verses[$id]['ref']=$ref;	
		$Verses[$id]['text']=$text;
		}
	}

#	Now we'll output the results. First a reminder of the keyword...
echo "<div style=\"width:400px;margin-left:50px;margin-bottom:20px\"><h3>keyword: &ldquo;$keyword&rdquo;</h3></div>";
#	Now the list (first 10) of the verses we found.
#	preg_quote() protects the pattern from the keyword. A keyword is text the visitor
#		typed, and a '.' or a '(' in it would either change what the pattern matches
#		or break it outright. The second argument tells preg_quote which delimiter
#		we are using, so it escapes that too.
$safe_keyword=preg_quote($keyword,'/');
foreach($Verses as $Verse)
	{
	$ref=$Verse['ref'];
	$text=$Verse['text'];
	# here's where we'll use preg_replace to find the keyword, make it bold and dark red.
	$text=preg_replace("/\b$safe_keyword\b/i", '<b style="color:#990000">$0</b>', $text);
	# Now we'll echo the verse with the reference.
	echo "<div style=\"width:400px;margin-left:50px;margin-bottom:20px\">
			&ldquo;$text&rdquo;&mdash;$ref</div>";	
	}

function getBooks()
	{
	global $_mysql;
	# The mysql query. No values are going in, so there is nothing to escape here.
	$querytext = "SELECT * FROM `kjv_books`;";
	# Submit the query
	$query=mysqli_query($_mysql, $querytext);
	# Check for errors
	if(mysqli_errno($_mysql))
		{
		# If there's an error, display it and the querytext for debugging
		echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
		}
	$bookName=[];$Books=[];$Bids=[];
	# If there's a result...
	if($query and mysqli_num_rows($query))
		{
		# 	Begin processing the results
		while ($dbRow = mysqli_fetch_assoc($query))
			{
		# 	Get the id of this row
			$id=$dbRow['id'];
		# 	Let's grab the book name, too.
			$book=$dbRow['book'];
		# 	Put the entire row into an array element
			$Books[$id]=$dbRow;
			$bookName[$id]=$dbRow['book'];
		# 	An easy array to find the book id from the name.	
			$Bids[$book]=$id;					
			}
		# Supposedly you're supposed to do this.
		mysqli_free_result($query);
		}
	# for now we are only returning the array of book names, because that's all we need.
	return $bookName;
	}

?>
