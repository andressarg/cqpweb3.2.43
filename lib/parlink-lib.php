<?php
/*
 * Parlink
 * Copyright (C) 2020 Andressa Rodrigues Gomide
 *
 */


/* this function calculates the number of tokens in the parlink window */
function calculate_tokens_in_parlink_window($dbname)
{
	$sql = "SELECT COUNT(*) from $dbname";

	return (int)get_sql_value($sql);
}


/* this function returns an sql query used to calculate the dice coeficient for parlinks */
function create_parlinkstats_sql_query($dbname, $alignment_att_to_show)
{
	global $Config; 
	global $Corpus;
	

	/* preparing variables for contingency table */
	$item = $dbname . ".word";
	$freq_table = "freq_corpus_" . $alignment_att_to_show . "_word";


	/* shorthand variables for contingency table */
	$N   = get_corpus_n_tokens($alignment_att_to_show);
	$R1  = calculate_tokens_in_parlink_window($dbname);
	$R2  = $N - $R1; 
	$C1  = "($freq_table.freq)";
	$C2  = "($N - $C1)"; 
	$O11 = "1e0 * COUNT($item)"; 
	$O12 = "($R1 - $O11)"; 
	$O21 = "($C1 - $O11)"; 
	$O22 = "($R2 - $O21)"; 
	$E11 = "($R1 * $C1 / $N)"; 
	$E12 = "($R1 * $C2 / $N)";
	$E21 = "($R2 * $C1 / $N)";
	$E22 = "($R2 * $C2 / $N)";


	/*
	
	
	2-by-2 contingency table
	
	--------------------------------
	|        | Col 1 | Col 2 |  T  |
	--------------------------------
	| Row 1  | $O11  | $O12  | $R1 |
	|        | $E11  | $E12  |     |
	--------------------------------
	| Row 2  | $O21  | $O22  | $R2 |
	|        | $E21  | $E22  |     |
	--------------------------------
	| Totals | $C1   | $C2   | $N  |
	--------------------------------
	
	parlink: word strongly associated with the query and possible translation for it
	
	N   = total words in target corpus (or the section) //todo make it work when restricted searches are perfomed
	C1  = frequency of the parlink in the target corpus
	C2  = frequency of words that aren't the parlink in the target corpus
	R1  = total words in the aligned sentences
	R2  = total words outside of aligned sentences
	O11 = how many of parlink there are in the aligned sentences
	O12 = how many words other than the parlink there are in the aligned sentences (calculated from row total)
	O21 = how many of parlink there are outside the aligned sentences
	O22 = how many words other than the parlink there are outside the aligned sentences
	E11 = expected values (proportion of parlink that would belong in aligned sentences if parlink were spread evenly)
	E12 =     "    "      (proportion of parlink that would belong outside aligned if parlink were spread evenly)
	E21 =     "    "      (proportion of other words that would belong in aligned sentences if parlink were spread evenly)
	E22 =     "    "      (proportion of other words that would belong outside aligned sentences if parlink were spread evenly)
	
	*/
	
	/* question: i have a problem here. if the same type occurs more than once in the
	 * same sentence, then the observed frequency is higher than it really is. 
	 * not sure if I have to fix this problem here or when creating the parlink database
	 */

		$result = do_sql_query("SELECT COUNT(DISTINCT refnumber) from $dbname");
		list($DICE_NODE_F) = mysqli_fetch_row($result);
		$P_COLL_NODE = "(COUNT(DISTINCT refnumber) / $DICE_NODE_F)";
		$P_NODE_COLL = "(COUNT($item) / ($freq_table.freq))";
		
		$sql = "select $item, count($item) as observed, $E11 as expected,
			2 / ((1 / $P_COLL_NODE) + (1 / $P_NODE_COLL)) as significance, 
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table 
			where $item = $freq_table.item
			group by $item
			order by significance desc";



	return $sql;
}


/* this function prepare the creation of the parlink database */
function prepare_parlink($n_key_items, $kwic, $alignment_att_to_show)
{

	// create an array with the aligned sentences
	$aligned_sentences = array();

	for ( $i = 0; $i < $n_key_items ; $i++ )
	{
		if($i % 2 != 0)
		{
			$aligned_sentences[] = get_aligned_line($kwic[$i], $alignment_att_to_show); 
		}
	}
///another for loop
/// if a sentence = 

	return $aligned_sentences;

}

function get_aligned_line($cqp_line,$alignment_att_to_show)
{

	/* Remove leading flag of alignment att from commandlne CQP */
	$line = preg_replace("/^-->{$alignment_att_to_show}:\s/", '', $cqp_line);
	
	
	if ('(no alignment found)' != $line)
	{

		/*question: should I include the if/else statement in case the corpus is not tagged?
		todo add the if else statement*/
		list($line) = concordance_line_blobprocess($line, 'aligned-tags', 100000);
		#list($line) = concordance_line_blobprocess($line, $tags_exist_in_aligned_cqp_output ? 'aligned-tags' : 'aligned-notags', 100000);
	}
	
	return $line;

}


/* this function creates the parlink table and return the 
types and their signicances to be used in the highlight function*/
function create_parlink_table($dbname, $alignment_att_to_show)
{

	// create and do sql query to calculate stats
	$sqlstats = create_parlinkstats_sql_query($dbname, $alignment_att_to_show);
	$result = do_sql_query($sqlstats);


	// create as associative array to store the types and their dice coefficient
	$type_significance = array();

	// create table head with a printing button
	echo <<<END_OF_ROW_HTML
	<div id="parlink_pop_table" class="concordance-popup">
	<a href="javascript:window.print()">&#128438;</a>
		<table>
			<tr>
				<td class="concordgrey" align="center">No.</td>
				<td class="concordgrey" align="center">Word</td>
				<td class="concordgrey" align="center">Total no. in corpus</td>
				<td class="concordgrey" align="center">Expected parallel link frequency</td>
				<td class="concordgrey" align="center">Observed parallel link frequency</td>
				<td class="concordgrey" align="center">In no. of texts</td>
				<td class="concordgrey" align="center">Dice coefficient</td>
			</tr>

END_OF_ROW_HTML;

	// prepare data for table and json
	$i = 0;
	while ($row = mysqli_fetch_assoc($result))
	{
		$i++;

		// populating associative array
		$type_significance[$row['word']] = $row['significance'];

		// populate table with values
		echo <<<END_OF_ROW_HTML
			<tr>
				<td class="concordgeneral" align="center">$i</td>
				<td class="concordgeneral" align="center">{$row['word']}</td>
				<td class="concordgeneral" align="center">{$row['freq']}</td>
				<td class="concordgeneral" align="center">{$row['expected']}</td>
				<td class="concordgeneral" align="center">{$row['observed']}</td>
				<td class="concordgeneral" align="center">{$row['text_id_count']}</td>
				<td class="concordgeneral" align="center">{$row['significance']}</td>
			</tr>

END_OF_ROW_HTML;

	}

	echo <<<END_OF_ROW_HTML
		</table>
	</div>
END_OF_ROW_HTML;

	return $type_significance;
}


/* this function highlight parlinks according to their strength */
function highlight_parlinks($line, $type_significance)
{
	if(isset($_POST['hi_button'])) 
	{ 
	
	/* change keys to lower case*/
	$type_significance = array_change_key_case($type_significance);
	$tokens_span = "";

	foreach(explode(' ', $line) as $tok)
	{		
		$tok_lower = strtolower($tok);
		if (array_key_exists($tok_lower, $type_significance))
		{
			ob_start();
			echo '<span style="background-color: hsl(107, 33%,' . parlink_luminosity_from_score($type_significance[$tok_lower]) . '%);">' . $tok . '</span> ';
			$tokens_span .= ob_get_clean();
		}
		else 
		{
			ob_start();
			echo '<span>' . $tok . '</span> ';
			$tokens_span .= ob_get_clean();
		}
	}
	return $tokens_span;
	}
}

/* this function transforms a score into an HSL lightness percentage*/
function parlink_luminosity_from_score($score)
{
     return ( (1 - $score) * 100 ) + 5;
}




