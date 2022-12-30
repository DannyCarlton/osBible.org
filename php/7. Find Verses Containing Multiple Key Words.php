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
 * Get a selection of verses based on a simple keyword.
 * 
 *****************************/

$Keywords=[];
$Notes=[];
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
	$_keyword=$keyword;$SearchKey=[];
	$note=$Note[$k];
	echo "<div style=\"float:left;width:380px;margin-left:30px;padding:15px;margin-bottom:10px;border:1px solid #000\">";
	$Verses=[];
		
	if(strstr($keyword,'"'))							// Catch quotes that will designate a phrase
		{
		preg_match_all('/"(.*?)"/',$keyword,$Matches); 	// This expression pulls any words within quote into the array Matches
		$newKeywords=$Matches[1];						// Now we have an array with the phrases.
			
		foreach($newKeywords as $kw)
			{
			$_kw=str_replace(' ','_',trim($kw));		//  We'll replace the space with an underline so we can avoid it later
			$keyword=str_replace($kw,$_kw,$keyword);	//		for examples 'David "loved her"' is now 'David "loved_her"' 
			}
		$keyword=str_replace('"','',$keyword);			// remove the quotes
		}

	if(strstr($keyword,' '))							// if we have multiple words...
		{
		$Keywords=explode(' ',$keyword);
		foreach($Keywords as $kw)
			{
			$kw=str_replace('_',' ',$kw);				//  return any underscores to spaces.
			$kw=str_replace('*','[a-zA-Z]*',$kw);		//  change any asterisks to a usable regular expression
			$SearchKey[]="`text` REGEXP '[[:<:]]".$kw."[[:>:]]'";		// We are creating an arrray of regular expressions
			}
		$search_key=implode(' AND ',$SearchKey);		// ...and linking them with 'AND' operators.
		}
	else
		{
		$keyword=str_replace('_',' ',$keyword);			// phrases are now turned back into teh correct form
		$keyword=str_replace('*','[a-zA-Z]*',$keyword);	// again, turn the asterisk inot the regular expression
		$search_key="`text` REGEXP '[[:<:]]".$keyword."[[:>:]]'";	//	Snce there were no spaces, wer only have one keyword (or phrase)
		}
	// 												Because of the strong's coding in 'kvvs' it's not usable for a complicated search.
	$querytext=sprintf("SELECT	* 		FROM	`kjv`
						WHERE	$search_key
						LIMIT	8;",
				mysqli_real_escape_string($_mysql, $keyword));

		# Submit the fquery
	$query = mysqli_query($_mysql, $querytext);
		# Receive the results
	if(mysqli_errno($_mysql))
		{
		# If there's an error, display it and the querytext for debugging
		echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
		}
	# If there's a result...
	if(mysqli_num_rows($query))
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

	#	Now we'll out put the results. Forst a reminder of the keyword...
	echo "<div style=\"width:380px;margin-bottom:20px;\"><h3>Example #$_k. $note<small style=\"font-weight:normal\"><br>keyword(s): $_keyword</small></h3></div>";
	#	Now the list (first 10) of the verse we found.
	foreach($Verses as $Verse)
		{
		$ref=$Verse['ref'];
		$text=$Verse['text'];
		# here's we'll use preg_replace to find the keyword, make it bold and dark red.
		if(strstr($_keyword,'"') or strstr($_keyword,'*'))
			{
			foreach($newKeywords as $kw)
				{
				$_kw=str_replace(' ','_',trim($kw));		//  We'll replace the space with an underline so we can avoid it later
				$keyword=str_replace($kw,$_kw,$keyword);
				}
			$Words=explode(' ',$keyword);
			foreach($Words as $word)
				{
				$word=str_replace('_',' ',$word);
				$text=preg_replace("/\b$word\b/i", '<b style="color:#990000">$0</b>', $text);
				}
			$text=preg_replace("/\b$keyword\b/i", '<b style="color:#990000">$0</b>', $text);
			}
		else
			{
			list($first,$second)=explode(' ',$keyword);
			$text=preg_replace("/\b$first\b/i", '<b style="color:#990000">$0</b>', $text);
			$text=preg_replace("/\b$second\b/i", '<b style="color:#990000">$0</b>', $text);

			}
		# Now we'll echo the verse with the reference.
		echo "<div style=\"width:380px;margin-bottom:20px\">
				&ldquo;$text&rdquo;&mdash;$ref</div>";	
		}
	echo "</div>\n";$newKeyords=[];$kw='';
	}



?>