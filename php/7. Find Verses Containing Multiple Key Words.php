<?php 
error_reporting(E_ALL);
echo "<h1>Find verses containing multiple key words.</h1>";
/*************************************************************************************
 * 
 * 	This script is to demonstrate a more complicated search using multiple keywords.
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
 * MariaDB and MySQL 5.7 and earlier use the POSIX markers [[:<:]] and [[:>:]].
 * MySQL 8.0.4 replaced the regex engine with ICU, which uses \b instead and rejects
 * 	the POSIX form outright with "repetition-operator operand invalid".
 * These two variables are the switch. See script 6 for the longer explanation.
 * 
 *****************************/

$WB_START='\b';			# MariaDB / MySQL 5.7:  '[[:<:]]'
$WB_END='\b';			# MariaDB / MySQL 5.7:  '[[:>:]]'


/*******************************
 * 
 * Get a selection of verses based on one or more keywords.
 * 
 *****************************/

$Keywords=[];
$Note=[];
$Keywords[0]='with love';
$Note[0]='Two keywords.';
$Keywords[1]='"loved her"';
$Note[1]='A two word phrase.';
$Keywords[2]='David "loved her"';
$Note[2]='A word and a phrase.';
$Keywords[3]='love*';
$Note[3]='Keyword with wild card.';


foreach($Keywords as $k=>$keyword)
	{
	$_k=$k+1;
	$_keyword=$keyword;					// keep the original, exactly as it was typed
	$SearchKey=[];						// the SQL conditions
	$Terms=[];							// the plain terms, for the highlighter
	$note=$Note[$k];
	$Verses=[];
	echo "<div style=\"float:left;width:380px;margin-left:30px;padding:15px;margin-bottom:10px;border:1px solid #000\">";

	if(strstr($keyword,'"'))							// Catch quotes that will designate a phrase
		{
		preg_match_all('/"(.*?)"/',$keyword,$Matches); 	// This expression pulls any words within quotes into the array Matches
		$Phrases=$Matches[1];							// Now we have an array with the phrases.
			
		foreach($Phrases as $kw)
			{
			$_kw=str_replace(' ','_',trim($kw));		//  We'll replace the space with an underline so we can avoid it later
			$keyword=str_replace($kw,$_kw,$keyword);	//		for example 'David "loved her"' is now 'David "loved_her"' 
			}
		$keyword=str_replace('"','',$keyword);			// remove the quotes
		}

	/*******************************
	 * 
	 * Build one condition per term.
	 * 
	 * The original had two branches here -- one for several words, one for a single
	 * 	word -- doing the same work twice. explode() on a string with no spaces returns
	 * 	a one element array, so the single word case is just the loop running once.
	 * 	One branch instead of two means a fix only has to be made in one place.
	 * 
	 * Two other things worth pointing out:
	 * 
	 * 1. The original wrote $Keywords=explode(...) INSIDE the foreach that is walking
	 * 		$Keywords. PHP iterates over a copy, so it happens to work, but overwriting
	 * 		the array you are looping over is a trap. It is $Words here.
	 * 
	 * 2. The original ran the query through sprintf() with mysqli_real_escape_string()
	 * 		as the argument -- but the format string had no %s in it, so the escaped
	 * 		value went nowhere and the terms reached the query unescaped. That is a real
	 * 		injection hole, hidden by code that looks like it is doing the right thing.
	 * 		Each term is escaped here instead, as it goes into its own condition.
	 * 
	 *****************************/

	$Words=explode(' ',$keyword);
	foreach($Words as $kw)
		{
		if($kw==''){continue;}
		$kw=str_replace('_',' ',$kw);					//  return any underscores to spaces.
		$Terms[]=$kw;									//  the term as it was typed, for the highlighter
		$pattern=str_replace('*','[a-zA-Z]*',$kw);		//  change any asterisks to a usable regular expression
														//  We are creating an array of regular expressions...
		$SearchKey[]="`text` REGEXP '".mysqli_real_escape_string($_mysql, $WB_START.$pattern.$WB_END)."'";
		}
	$search_key=implode(' AND ',$SearchKey);			// ...and linking them with 'AND' operators.

	// 												Because of the strong's coding in `kjvs` it's not usable for a complicated search,
	//													so we use `kjv`, which holds the plain text.
	$querytext="SELECT	* 		FROM	`kjv`
				WHERE	$search_key
				LIMIT	8;";

		# Submit the query
	$query = mysqli_query($_mysql, $querytext);
		# Receive the results
	if(mysqli_errno($_mysql))
		{
		# If there's an error, display it and the querytext for debugging
		echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
		}
	# If there's a result...
	if($query and mysqli_num_rows($query))
		{
		# 	Begin processing the results
		while ($dbRow = mysqli_fetch_assoc($query))
			{
		# 	Get the id of this row
			$id=$dbRow['id'];
		#	get the book name
			$book=$dbRow['book'];
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
	echo "<div style=\"width:380px;margin-bottom:20px;\"><h3>Example #$_k. $note<small style=\"font-weight:normal\"><br>keyword(s): $_keyword</small></h3></div>";

	/*******************************
	 * 
	 * The highlighter.
	 * 
	 * The original rebuilt the phrase-to-underscore juggling all over again down here,
	 * 	then branched three ways on whether the keyword had quotes, an asterisk or a
	 * 	space -- and the last branch used list($first,$second) on an explode, which
	 * 	raises a warning the moment there is only one word. We already collected the
	 * 	terms while building the query, so we can just walk them.
	 * 
	 * preg_quote() keeps a term from being read as a pattern. Then the asterisk is put
	 * 	back deliberately, because a wildcard search should highlight the whole word it
	 * 	matched, not just the stem.
	 * 
	 *****************************/

	foreach($Verses as $Verse)
		{
		$ref=$Verse['ref'];
		$text=$Verse['text'];
		foreach($Terms as $term)
			{
			$pattern=str_replace('\*','[a-zA-Z]*',preg_quote($term,'/'));
			$text=preg_replace("/\b$pattern\b/i", '<b style="color:#990000">$0</b>', $text);
			}
		# Now we'll echo the verse with the reference.
		echo "<div style=\"width:380px;margin-bottom:20px\">
				&ldquo;$text&rdquo;&mdash;$ref</div>";	
		}
	echo "</div>\n";
	}

?>
