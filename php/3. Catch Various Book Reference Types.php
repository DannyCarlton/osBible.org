<?php 
/*************************************************************************************
 * 
 * 	This script is to demonstrate how to catch a variety of reference types including 
 * 		abbreviations and misspellings.
 *	It will used the function getBookByKeyword which takes what is offered as the 
 *		book, whether it's the full name, an abbreviation or a misspelling and 
 *		return the data contained on the kjv_books database for that book.
 *	We will also use the function putNumRefInArray to take the numeric part of the 
 *		reference and return an array of verses.
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
$Note[1]='A typical abbreviation';
$References[1]='Jn 3:16';
$Note[2]='Several examples of how books with numbers are entered. Just a number';
$References[2]='2 pt 1:1';
$Note[3]='...Number with ordinal';
$References[3]='2nd pt 1:1';
$Note[4]='...Roman numeral';
$References[4]='ii pt 1:1';
$Note[5]='...Ordinal word';
$References[5]='Second Peter 1:1';
$Note[6]='Common mispelling';
$References[6]='gnesis 1:1';

foreach($References as $n=>$reference)
	{
	# We'll reserve the original reference
	$_reference=$reference;
	# We;ll grab the note to display
	$note=$Note[$n];
	# To avoid confusing the filter we'll make all book references lower case
	$reference=strtolower($reference);
	# We'll use the function to split the book part from the nymber part and return it all in an array
	$BookData=getRefByKeyword($reference);
	# We want the book id for the next database query
	$bid=$BookData['id'];
	# Here we'll have the book name
	$book=$BookData['book'];
	# We'll split the chapter from the verse
	list($chapter,$verse)=explode(':',$BookData['num_key']);
	# Now we'll build our query... 
	$querytext=sprintf("SELECT	`text` 		FROM	`kjvs`
						WHERE	`book` 		= 		'%s'
						AND 	`chapter`	=		'%s'
						AND		`verse`		=		'%s'
						LIMIT	1;",
						mysqli_real_escape_string($_mysql, $bid),
						mysqli_real_escape_string($_mysql, $chapter),
						mysqli_real_escape_string($_mysql, $verse));
	# Submit the query
	$query = mysqli_query($_mysql, $querytext);
	# Receive the results
	$Verse = mysqli_fetch_array($query);
	# Assign the data to $text
	$text=$Verse['text'];
	# Filter out the Strong's numbers (we don't need them now)
	$text=preg_replace('/\{(.*?)\}/', '', $text);
	# Echo the original reference with the verse and correct reference
	echo "Original reference: $_reference ($note)<br>Result: &ldquo;$text&rdquo;&mdash;$book $chapter:$verse<hr>";	
	}




function getRefByKeyword($k)
	{
	# Bring our databse connection within the scope of the function
	global $_mysql;
	# Set some variables
	$Return['id']=0;
	$Return['book']='';
	$Return['chapters']=0;
	# Clean any oddities brought over in a url
	$_k=urldecode($k);
	# Remove extra spaces
	$_k=preg_replace("/(\s){2,}/", ' ', $_k); 
	# Remove periods
	$_k=str_replace('.','',$_k);
	# Make ordinals regular numbers (note, here we are expecting a space between the ordinal and the book name)
	$_k=str_replace('1st ', '1 ', $_k);
	$_k=str_replace('2nd ', '2 ', $_k);
	$_k=str_replace('3rd ', '3 ', $_k);
	$_k=str_replace('first ', '1 ', $_k);         
	$_k=str_replace('second ', '2 ', $_k);
	$_k=str_replace('third ', '3 ', $_k);
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
	# 	We "score" the macthes so that the one with the best "score" rises to the top and we get it.
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
			$Return['dbRow']=$dbRow;
			$c++;
			if($dbRow['book']=='Psalms'){$dbRow['book']='Psalm';}
			$Return['id']=$dbRow['id'];
			$Return['book']=$dbRow['book'];
			$Return['chapters']=$dbRow['chapters'];
			}
		}		
	$Return['queryText']=$queryText;
	return $Return;
	}



?>