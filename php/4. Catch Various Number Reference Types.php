<?php 


#error_reporting(E_PARSE | E_ERROR);
error_reporting(E_ALL);
/*************************************************************************************
 * 
 * 	This script is to demonstrate how to catch a variety of numeric (chapter and verse) reference types 
 * 		including multiple verses, multiple chapters, lists and others..
 *	It will used the function getBookByKeyword which takes what is offered as the 
 *		book, whether it's the full name, an abbreviation or a misspelling and 
 *		return the data contained on the kjv_books database for that book.
 *	We will also use the function putNumRefInArray to take the numeric part of the 
 *		reference and return an array of correct verses.
 * 
 ***************************************************************************************/

/*******************************
 * 
 * Connect to the database. 
 * For demonstration purposes I actually used db_user and db_password for my local intranet.
 * 	You'll want to use something more secure.
 * The database is named `osbible` (Yours may be different.)
 * 
 *****************************/

  # $_mysql will be our db connection variable. Mysqli requires it for almost everything.
$_mysql = mysqli_connect('localhost','db_user','db_password','osbible');


	# We will start with a few typical passage reference.
	# 	We will put them in anarray because we'll used several of them to demostrate various forms of references.

$References=[];
$Note[]='A book with one chapter that the reference targets the verse';
$References[]='3 John 3';
$Note[]='A book with multiple chapters referencing the entire chapter';
$References[]='John 3';
$Note[]='Verse not in sequence';
$References[]='Eph 2:8,9,12';
$Note[]='List with additional verse';
$References[]='Eph 2:8-10,12';
$Note[]='Verses across several chapters';
$References[]='Luke 7:12; 8:42; 9:38';
$Note[]='Verses across several chapters (but ending with a stray semi-colon)';
$References[]='Luke 7:12; 8:42; 9:38;';
$Note[]='Using a period instead of a colon';
$References[]='John 3.16';

foreach($References as $n=>$reference)
	{
	# initialize some variables
	$_c=0;$corrected_ref='';$chapters=1;
	# create a visual divider between references and results
	echo "<hr>";	
	# We'll reserve the original reference
	$_reference=$reference;
	# We'll grab the note to display
	$note=$Note[$n];
	# To avoid confusing the filter we'll make all book references lower case
	$reference=strtolower($reference);
	# We'll use the function to split the book part from the number part and return it all in an array
	$BookData=getBookByKeyword($reference);
	echo "<b>reference: $reference</b> ($note)<br>";
	# We want the book id for the next database query
	$bid=$BookData['bid'];
	# Here we'll have the book name
	$book=$BookData['book'];
	# Sending the entire $BookData array provides all the info we need to process the number keys
	$Verses=putNumRefInArray($BookData);
	# the numKeys is an array of the passages requested (we'll still need to process the verse portion of each passage)
	$numKeys=$Verses['numKeys'];
	# initialize/reset some variables
	$Result=[];	$out='';$old_chapter=0;	$out='';
	# run through the number keys
	foreach($numKeys as $n=>$numKey)
		{
		# get the chapter
		$chapter=$numKey['chapter'];
		# if it's different from the previous chapter, count the chapters
		if($old_chapter and ($chapter!=$old_chapter))
			{
			$chapters++;
			}
		$old_chapter=$chapter;
		$verses=$numKey['verses'];
		$corrected_ref.="$book $chapter:$verses; ";
		# now we need to determine if it's just one verse, verses divided by commas, a list with a dash or in some cases, both
		$verseList=[];
		if(strstr($verses,','))
			{
			$_Verses=explode(',',$verses);
			foreach($_Verses as $verse)
				{
				if(strstr($verse,'-'))
					{
					list($start,$end)=explode('-',$verse);
					for($i=$start;$i<=$end;$i++)
						{
						$verseList[]=$i;
						}
					}
				else
					{
					$verseList[]=$verse;
					}
				}
			}
		elseif(strstr($verses,'-'))
			{
			list($start,$end)=explode('-',$verses);
			for($i=$start;$i<=$end;$i++)
				{
				$verseList[]=$i;
				}
			}
		else
			{
			$verseList[]=$verses;
			}
		foreach($verseList as $v)
			{
			# Now we'll build our query... 
			$querytext=sprintf("SELECT	* 		FROM	`kjvs`
								WHERE	`book` 		= 		'%s'
								AND 	`chapter`	=		'%s'
								AND		`verse`		=		'%s'
								LIMIT	1;",
								mysqli_real_escape_string($_mysql, $bid),
								mysqli_real_escape_string($_mysql, $chapter),
								mysqli_real_escape_string($_mysql, $v));
			# Submit the query
			$query = mysqli_query($_mysql, $querytext);
			# Receive the results
			$Verse = mysqli_fetch_array($query);
			# Assign the data to $text
			$text=$Verse['text'];
			# Filter out the Strong's numbers (we don't need them now)
			$text=preg_replace('/\{(.*?)\}/', '', $text);
			$Verse['text']=$text;
			$Verse['book']=$book;
			$Result[]=$Verse;
			$out.="<div><b>$chapter:$v.</b> $text</div>";
			$Out[$_c]['book']=$book;
			$Out[$_c]['chapter']=$chapter;
			$Out[$_c]['verse']=$Verse['verse'];
			$Out[$_c]['text']=$text;
			$_c++;
			}
		}
	$corrected_ref=rtrim($corrected_ref,'; ');
	# Echo the original reference with the verse and correct reference
	if($chapters==1 and count($Out)==1)
		{
		echo "<b>$corrected_ref</b><br>&ldquo;{$Out[0]['text']}&rdquo;";
		}
	elseif($chapters==1)
		{
		echo "<b>$corrected_ref</b><br>";
		foreach($Out as $Verse)
			{
			echo "<b>{$Verse['verse']}</b> {$Verse['text']}<br>";
			}
		}
	else
		{
		echo "<b>$corrected_ref</b><br>";
		foreach($Out as $Verse)
			{
			echo "<b>{$Verse['chapter']}:{$Verse['verse']}</b> {$Verse['text']}<br>";
			}

		}
	$Out=[];
	}




function getBookByKeyword($k)
	{
	# Bring our databse connection within the scope of the function
	global $_mysql;
	# Set some variables
	$Return['bid']=0;
	$Return['book']='';
	$Return['chapters']=0;
	# Clean any oddities brought over in a url
	$_k=urldecode($k);
	# Remove extra spaces
	$_k=preg_replace("/(\s){2,}/", ' ', $_k); 
	# Change periods to colons
	$_k=str_replace('.',':',$_k);
	# remove spaces from verse separattor (;)
	$_k=str_replace('; ',';',$_k);
	# Make ordinals regular numbers (note, here we are expecting a space between the ordinal and the book name)
	$_k=str_replace('1st ', '1 ', $_k);
	$_k=str_replace('2nd ', '2 ', $_k);
	$_k=str_replace('3rd ', '3 ', $_k);
	$_k=str_replace('first ', '1 ', $_k);         
	$_k=str_replace('second ', '2 ', $_k);
	$_k=str_replace('third ', '3 ', $_k);
	# By using the /i we catch upper and lower case instances
	$_k=preg_replace('/^i /i', "1 ", $_k); 
	$_k=preg_replace('/^ii /i', "2 ", $_k);
	$_k=preg_replace('/^iii /i', "3 ", $_k);

	# Catch messed up beginning numbers
	# 	This catches initial numbers imediately followed by text, as in 1pe or 2jn
	if(preg_match('/^[1-3][a-zA-Z]/',$_k))
		{
		$_k=preg_replace('/^1/', '1 ',$_k);
		$_k=preg_replace('/^2/', '2 ',$_k);
		$_k=preg_replace('/^3/', '3 ',$_k);
		}
	# Explode by spaces
	$Ref_keys=explode(' ',$_k);
	# If first element is empty, remove (this happens whe the reference begins with a space)
	if(!$Ref_keys[0])
		{
		$toss=array_shift($Ref_keys);
		}
	# If first element is a number, combine with second to make the book key
	if(isset($Ref_keys[1]) and preg_match('/^[1-9]/',$Ref_keys[0]))
		{
		$book_key=$Ref_keys[0].' '.$Ref_keys[1];
		$num_keys=$Ref_keys[2];
		}
	else
		{
		$book_key=$Ref_keys[0];
		$num_keys=$Ref_keys[1];
		}
	# Add the chapter and verse(s) to the output as 'num_key'
	$Return['num_key']=$num_keys;
	# I don't remember why, but I found this necessary
	if(strtolower($book_key)=='jud'){$book_key='Judges';}
	if(strtolower($book_key)=='eph'){$book_key='Ephesians';}
		
	$_c=0;
	# Let's explain the query... 
	# 	First we look in the `abbr` column for matches. Theindividual abbreviations are delimited by | 
	# 	We "score" the matches so that the one with the best "score" rises to the top and we get it.
    $queryText = sprintf("SELECT * FROM `kjv_books` WHERE `abbr` LIKE '%%|%s|%%' || `book` LIKE '%s' || `kjav_abr` LIKE '%s' || `book` SOUNDS LIKE '%s'
                         ORDER BY
                         case when `abbr` LIKE '%%|%s|%%' then 4 else 0 end
                       + case when `book` LIKE '%s' then 3 else 0 end
                       + case when `kjav_abr` LIKE '%s' then 2 else 0 end
                       + case when `book` SOUNDS LIKE '%s' then 1 else 0 end
                         DESC LIMIT 1",
                 mysqli_real_escape_string($_mysql,$book_key),
                 mysqli_real_escape_string($_mysql,$book_key),
                 mysqli_real_escape_string($_mysql,$book_key),
                 mysqli_real_escape_string($_mysql,$book_key),
                 mysqli_real_escape_string($_mysql,$book_key),
                 mysqli_real_escape_string($_mysql,$book_key),
                 mysqli_real_escape_string($_mysql,$book_key),
                 mysqli_real_escape_string($_mysql,$book_key));
	$query=mysqli_query($_mysql,$queryText);
	if(mysqli_errno($_mysql)){echo ": " . mysqli_error($_mysql) . "<br>$queryText\n<hr>";}
	if(mysqli_num_rows($query))
		{
		$c=0;
		while ($dbRow = mysqli_fetch_assoc($query)) 
			{
			$c++;
			if($dbRow['book']=='Psalms'){$dbRow['book']='Psalm';}
			$Return['bid']=$dbRow['id'];
			$Return['book']=$dbRow['book'];
			$Return['chapters']=$dbRow['chapters'];
			}
		}		
	return $Return;
	}


function putNumRefInArray($BookData)
	{
	# Bring our database connection within the scope of the function
	global $_mysql;
	$Return['BookData']=$BookData;
	# Get the book
	$bid=$BookData['bid'];
	$book=$BookData['book'];
	# Get the number of chapters in that book
	$num_key=$BookData['num_key'];
	# Is it a single number? By changing it to an interger, it will either remove other characters or set it to zero
	$_num_key=(int)$num_key;
	if($num_key==$_num_key)
		{
	# 	If yes... 
	# 		Does the book have a single chapter?
		if($BookData['chapters']==1)
			{
	# 		If yes, is this number greater than 1?
	# 			...then make it 1:n 
			$num_key="1:$_num_key";
			$Refs[0]=$num_key;
			}
		else
			{
	#		If no, then the ref is c:1-(end of chapter)
			$verse_count=getVerseCount($bid,$num_key);
			$num_key="$num_key:1-$verse_count";
			$Refs[0]=$num_key;
			}
		}
	else
		{
	#if a semi-colon is present, separate passages
		if(strstr($num_key,';'))
			{
	# catch lists that ended with a semi-colon
			$num_key=rtrim($num_key,';');
			$Refs=explode(';',$num_key);
			}
		else
			{
			$Refs[0]=$num_key;
			}			
		}
	$Return['Refs']=$Refs;
	$numKeys=[];
	foreach($Refs as $f=>$ref)
		{
	# Separate the initial chapter from the rest.
		list($chapter,$verses)=explode(':',$ref);
		$numKeys[$f]['chapter']=$chapter;
		$numKeys[$f]['verses']=$verses;
		}
	$Return['numKeys']=$numKeys;
	return $Return;
	}


function getVerseCount($bid,$chapter)
	{
	# Bring our database connection within the scope of the function
	global $_mysql;
	$querytext=sprintf("SELECT COUNT(`verse`) AS `versecount` FROM `kjvs` WHERE `book`='%s' AND `chapter`='%s';",
		mysqli_real_escape_string($_mysql, $bid),
		mysqli_real_escape_string($_mysql, $chapter));
	$query=mysqli_query($_mysql,$querytext);
	$Result = mysqli_fetch_array($query);
	return $Result['versecount'];
	}

?>