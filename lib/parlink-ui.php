<?php
/*
 * Parlink
 * Copyright (C) 2020 Andressa Rodrigues Gomide
 */



/** 
 * @file 
 * This file contains the code for creating parlink databases and then creating a parallel concordance display.
 */



/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* include function library files */
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/sql-lib.php');
require('../lib/html-lib.php');
require('../lib/concordance-lib.php'); 
require('../lib/postprocess-lib.php'); 
require('../lib/parlink-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/xml-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/cache-lib.php');
require('../lib/scope-lib.php');
require('../lib/db-lib.php');
require('../lib/rface.inc.php');
require('../lib/cqp.php');


/* declare global variables */
$Corpus = $User = $Config = NULL;




cqpweb_startup_environment();




/* ------------------------------- *
 * initialise variables from $_GET *
 * and perform initial fiddling    *
 * ------------------------------- */


/* variables from collocation-options *
 * ---------------------------------- */


/* this script is passed qname from the collocation-options */
$qname = safe_qname_from_get();

//TODO shorter param names, e.g. wBegin, wEnd, minC, minNC, maxSpan, att , stat....


/* creating the DB - start *
 * ----------------------- */ 
//just for now
$Corpus->visualise_translate_in_concordance = true;
$view_mode = 'line';
$show_all_hits = true;


//find the aligned corpus to which it is aligned:
$align_info = check_alignment_permissions(list_corpus_alignments($Corpus->name));
$alignment_att_to_show = $_GET['showAlign']; 

// cache_record and number of solutions
$cache_record = QueryRecord::new_from_qname($qname);
$n_of_solutions_final = $cache_record->hits();

// get the content of the aligned texts
$cqp = get_global_cqp();
$cqp->execute("set Context {$Corpus->conc_scope} words");
$cqp->execute('show +' . $alignment_att_to_show);

$batch_start = 0;
$batch_end   = $n_of_solutions_final;
// source + aligned text
$kwic = $cqp->execute("cat $qname $batch_start $batch_end");
//QUESTION: does it make sense to use CQP 'translating' functionality? 

//get number of total results
$n_key_items = count($kwic);
$n_aligneds = $n_key_items/2; 


// number of texts with a parlink
$n_of_texts = count( $cqp->execute("group $qname match text_id")); //todo: do i need this here?

// get text ids
$textidsarray = $cqp->execute("tabulate $qname match text_id");

//create an array with the aligned sentences
$targettxt = array();

for ( $i = 0; $i < $n_key_items ; $i++ )
{
	if($i % 2 != 0)
	{
		$targettxt[] = preg_replace("/^-->.*:\s/", '', $kwic[$i]);
	}
}

//create a table with text_id, refnumber and word
//todo: add it to db-lib
$dbname = "dbparlink";
do_sql_query("DROP TABLE if exists $dbname");

$sql1 = "CREATE TABLE $dbname (
			text_id varchar(255) NOT NULL,
			refnumber bigint unsigned NOT NULL,
			word varchar(40) NOT NULL,
			key (refnumber)
			) CHARACTER SET utf8 COLLATE {$Corpus->sql_collation}";

do_sql_query($sql1);
disable_sql_table_keys($dbname);

//populate table with text_id from the source, ref number and word
for($i = 0; $i < $n_key_items/2; $i++)
{
	$word = explode(" ", $targettxt[$i]);
	$word = array_map('strtolower', $word);

	for($j =0; $j < count($word); $j++)
	{
		$sql1 = "INSERT INTO $dbname VALUES('$textidsarray[$i]', $i, '$word[$j]');";
		do_sql_query($sql1);
	}
}


///////////////todo do i use any of the below?
//create an array with aligned types and their frequencies
$lista = array();

for($i = 0; $i < $n_key_items/2; $i++)
{
	//get the types and change to lowercase
	//QUESTION would that be a problem for certain languages?
	$word = explode(" ", $targettxt[$i]);
	$word = array_map('strtolower', $word);

	for($j = 0; $j < count($word); $j++)
	{
		if(array_key_exists($word[$j], $lista))
		{
			$lista[$word[$j]] += 1;
		} else {
			$lista[$word[$j]] = 1;
		}
	}
}


// assign a name for the table and drop if it existis
// creating frequency list table
$sql_parlink = "testeparlink";
do_sql_query("DROP TABLE if exists $sql_parlink");

//create sql table to store types and their frequencies
$sql = "CREATE TABLE $sql_parlink (
	item varchar(255) NOT NULL,
	freq int(11) unsigned default NULL,
	primary key(item)
	) CHARACTER SET utf8 COLLATE {$Corpus->sql_collation}";

do_sql_query($sql);
disable_sql_table_keys($sql_parlink);

//adding values to sql table
//QUESTION what is the most efficient way of doing this?
for($i=0; $i<count($lista); $i++)
{
	// $it = array_keys($lista)[$i];
	$it = utf8_encode(array_keys($lista)[$i]); //QUESTION without this, types like 'é' and 'e' were considered the same
	$fr = array_values($lista)[$i];
	$sql = "INSERT INTO $sql_parlink VALUES('$it', $fr);";
	do_sql_query($sql);
}


///////////////todo do i use any of the above???



/* $colloc_atts -- a list of attributes separated by ~ 
 * $colloc_atts_list -- same thing as an array 
 */
$colloc_atts = '';
$colloc_atts_list = array();


/* note that this has been set up so that incoming is the same from colloc-options and from self */
if ( 0 < preg_match_all('/collAtt_(\w+)=1/', $_SERVER['QUERY_STRING'], $match, PREG_PATTERN_ORDER) )
{
	foreach ($match[1] as $m)
		$colloc_atts_list[] = $m;
	sort($colloc_atts_list);
	$colloc_atts = '~~';			/* nonzero string signals that a check is needed, see below */
}



/* colloc_range --- the length of aligned sentence TODO: state the parameter for the aligned sentence (in defaults.php) */
// $colloc_range = $Config->default_parlink_range;
//just for tests
$colloc_range = 4;
$parlink_range = 4;




/* parameters unique to this script *
 * -------------------------------- */

/* variables that come from the collocation control form and only affect "this" calculation */

/* note that "calc" in a variable name indicates it is to be used for display,
 * as opposed to variables that are to be used for the database creation & db cache-retrieval */


/* the p-attribute to be used for this script's calculation word */
$att_for_calc = 'word';
// todo allow admin to choose lemma in the future


/* Window span for the calculation */
$calc_range_begin = ($colloc_range > 2 ? -($colloc_range - 2) : $colloc_range);
$calc_range_end = -($calc_range_begin);



/* minimum frequencies for the to be considered */
$calc_minfreq_together = $Config->default_colloc_minfreq;
$calc_minfreq_collocalone = $Config->default_colloc_minfreq;


/* are we to use the overall freq table for the corpus EVEN IF a subsection is specified ? */
if (isset($_GET['freqtableOverride']) )
	$freq_table_override = (bool)$_GET['freqtableOverride'];
else
	$freq_table_override = false;




/* is this the "collocation solo" function? */
if (!empty($_GET['collocSolo']))
{
	$soloform = $_GET['collocSolo'];
	$solomode = true;
}
else
	$solomode = false;



/* and a purely display-related variable */
if (isset($_GET['beginAt']) )
	$begin_at = abs((int) $_GET['beginAt']);
else
	$begin_at = 0;


/* are we to use the overall freq table for the corpus EVEN IF a subsection is specified ? */


/* stats */


$statistic = load_statistic_info();



/* calc_stat is the index of the statistic to be used for the collocation table below,
   to be used as an index into the array created above */
   if ( isset($_GET['collocCalcStat']) )
   $calc_stat = (int) $_GET['collocCalcStat'];
else if ( isset($User->coll_statistic) )
   $calc_stat = (int)$User->coll_statistic;
else
   $calc_stat = $Config->default_colloc_calc_stat;

if (! isset($statistic[$calc_stat]) )
   /* non-existent stat, so go to default */
   $calc_stat = $Config->default_colloc_calc_stat;



/* tag_filter - do not consider tags for parlink */
$tag_filter = false;
$display_tag_filter_control = false;

/* -------------------------- *
 * end of variable initiation *
 * -------------------------- */




$att_desc = list_corpus_annotations($Corpus->name);
$att_desc['word'] = 'Word';




$att_for_calc = 'word';
//just for tests
$calc_range_begin = -2;
$calc_range_end = 2;



$start_time = microtime(true);


/* does a db for the collocation exist? */

/* first get all the info about the query in one handy package */

$query_record = QueryRecord::new_from_qname($qname);
if ($query_record === false)
	exiterror("The specified query $qname was not found in cache!");


/* now, search the db list for a db whose parameters match those of the query
 * named as qname; if it doesn't exist, we need to create one */
$type_spec = new DbType(DB_TYPE_PARLINK);
$db_record = check_dblist_parameters($type_spec, $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);
// 				$query_record->restrictions, $query_record->subcorpus, $query_record->postprocess,
// 				$query_record->query_scope, $query_record->postprocess,
// 				$colloc_atts, $colloc_range);

if ($db_record === false)
{
	$dbname = create_db($type_spec, $qname, $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);
	$db_record = check_dblist_dbname($dbname);
	$is_new_db = true;
}
else
{
	$dbname = $db_record['dbname'];
	touch_db($dbname);
	$is_new_db = false;
}
/* this dbname & its db_record can be globalled by print functions in the script */



/* for now just find out how many distinct items it has in it */
$db_types_total = mysqli_num_rows(do_sql_query("select distinct(`$att_for_calc`) from $dbname"));


/* OK we have the db for calculating collocations, now we need the basis of comparison */

// /* first: check if there is a reduced restriction n the query record (from a "dist" postprocess.) */
// $reduced_restrictions = $query_record->get_reduced_restrictions();

//TODO: The following explains how it will have to work ultimately.
/*

notes on how this works in the 3.2.7 revisions.
================================================================

FIRST: ask the query record, does it have any distribution-type postprocesses.

IF IT DOES, create a query scope representing the "extra". ($^--text|class~cat.class~cat.class~cat...........
 	(this can be done for calling a m  ethiod "represent dist reductions as scope", and if the new scope is whole corpus, theer are none. As follows:
 
$dist_scope = $query_record->get_dist_reductions_as_scope();
if (QueryScope::TYPE_WHOLE_CORPUS == $dist_scope->type)
	;// no dist type postprocesses
else
	;// see below.

THEN do a query-scope intersect for that "extra" and the original query-scope.
and do the get/make-freqtable stuff on the intersected scope. 


FOR NOW: 

	let the intersection procedur3e return false. IF IT DOES, then we just fall back to using the whole corpus method.
	DO THIS if any kind of intersection is required OTHER THAN a --text restriction with another --text restriction.
	
if (QueryScope::TYPE_WHOLE_CORPUS == $dist_scope->type || false === whatever_the_intersect_func_is($query_record->qscope, $dist_scope) )

AND review whether intersect-create methods could do the jobs currenlty done by other methods in the Distribution func. 

 */

$dist_scope = $query_record->get_extra_filters_as_qscope();

/* can we get an intersect for the $dist_scope and the original scope? */
if (QueryScope::TYPE_WHOLE_CORPUS == $dist_scope->type)
	$intersect_result_scope = false;
else
	$intersect_result_scope = $query_record->qscope->get_intersect($dist_scope);




// if (empty($reduced_restrictions))
if (false == $intersect_result_scope)
{
	/* CODE FOR SITUATION WHERE THERE ARE NO DIST-TYPE POSTPROCESSES (i.e. the original case) */
	
	/* if there is a subcorpus or restriction, then a table for that needs to be found or created */
	if (QueryScope::TYPE_WHOLE_CORPUS != $query_record->qscope->type)
	{
		if ( false !== ($freqtable_record = $query_record->qscope->get_freqtable_record()) ) 
			;
		else
		{
			/* if not found, and if the subsection is not too big, create it */
			$words_in_subsection = $query_record->get_tokens_searched_initially();
			$freq_table_override = ( $words_in_subsection < $Config->collocation_disallow_cutoff ? $freq_table_override : true );
	
			if ( ! $freq_table_override )
				$freqtable_record = subsection_make_freqtables($query_record->qscope);
		}
	}
	
// 	if (!empty($query_record->subcorpus))
// 	{
// 		/* has this subcorpus had its freqtables created? */
// 		$sc = Subcorpus::new_from_id($query_record->subcorpus);
// 		if (false == $sc)
// 			exiterror("The subcorpus in which this query ran seems to have been deleted."); // necessary?
// 		if ( false !== ($freqtable_record = $sc->get_freqtable_record()))
// 			;
// 		else
// 		{
// 			/* if not, and if the subsection is not too big, create it */
// 			$words_in_subsection = $query_record->get_tokens_searched_initially();
// 			$freq_table_override = ( $words_in_subsection < $collocation_disallow_cutoff ? $freq_table_override : true );
	
// 			if ( ! $freq_table_override )
// 				$freqtable_record = subsection_make_freqtables($query_record->subcorpus);
// 		}
// 	}
// 	else if ($query_record->restrictions != '')
// 	{
// 		/* search for a freqtable matching that restriction */
// 		if ( false !== ($freqtable_record = check_freqtable_restriction($query_record->qscope->serialise())) )
// 			;
// 		else
// 		{
// 			/* if there isn't one, and if the subsection is not too big, create it */
// 			$words_in_subsection = $query_record->get_tokens_searched_initially();
// 			$freq_table_override = ( $words_in_subsection < $collocation_disallow_cutoff ? $freq_table_override : true );
	
// 			if ( ! $freq_table_override )
// 				$freqtable_record = subsection_make_freqtables('', $query_record->qscope->serialise());
// 		}
// 	}
}
else
{
	/* CODE FOR SITUATION WHERE THERE IS ONE OR MORE DIST-TYPE POSTPROCESSES */

	/* same as above, but simplified because it CAN'T be a subcorpus; also note that we use the reduced token count
	 * not the initial token count for checking for a freq-table-override.  */
// 	if ( false !== ($freqtable_record = check_freqtable_restriction($reduced_restrictions)) )
	if ( false !== ($freqtable_record = $intersect_result_scope->get_freqtable_record()) )
		;
	else
	{
		$words_in_subsection = $query_record->get_tokens_searched_reduced();
		$freq_table_override = ( $words_in_subsection < $Config->collocation_disallow_cutoff ? $freq_table_override : true );
	
		if ( ! $freq_table_override )
// 			$freqtable_record = subsection_make_freqtables('', $reduced_restrictions);
			$freqtable_record = subsection_make_freqtables($intersect_result_scope);
	}
}

/* nb: freq_table_override is tested in the ELSE condition above. This means that if the override is
 * set to TRUE, but by some chance the freqtable necessary does exist, the override does not kick in.
 * 
 * Note also, if the override is activated, $freqtable_record WON'T be set.
 */


if ( !empty($freqtable_record) )  /* ie (a) IF the if above was true.... */
{
 	/* we are using a subsection (sc or retriction): touch it and assign the freqtable name */
	$freq_table_to_use = "{$freqtable_record->freqtable_name}_{$att_for_calc}";
	$desc_of_basis = 'this subcorpus';
	touch_freqtable($freqtable_record->freqtable_name);
}
else
{
	/* we are not using a subsection, so default to the table for this corpus */
	/* OR the override for a too-much-time-to-calculate subcorpus was engaged */
	$freq_table_to_use = "freq_corpus_{$Corpus->name}_{$att_for_calc}";
	/* this variable is not used here, but IS used in create_statistic_sql_query() */
	$desc_of_basis = 'whole corpus';
}



/* ------------------------------------------------------------------------------------- *
 * send the script off to the separate function if it is the collocation-solo capability *
 * ------------------------------------------------------------------------------------- */

/* this array wraps up all the script options for assorted functions. */
$calc_options = compact(explode(' '
		, 'dbname freq_table_to_use calc_stat att_for_calc calc_range_begin calc_range_end calc_minfreq_collocalone calc_minfreq_together tag_filter download_mode soloform begin_at'));

if ($solomode === true)
{
	run_script_for_solo_collocation($query_record, $calc_options);
	echo print_html_footer('collocation');
	cqpweb_shutdown_environment();
	exit(0);
}



/* run the BIIIIIIIIIIIIG mysql query */

$sql = create_parlinkstats_sql_query($calc_options, $sql_parlink, $n_aligneds);

$result = do_sql_query($sql);

// var_dump($result);
// print_r($result);
// print_r(mysqli_fetch_assoc($result));

// $outp = $result->fetch_all(MYSQLI_ASSOC);
// echo json_encode($outp);


/* "time" == time to create the db (if nec), create the freqtable (if nec), + run the BIIIG query */
$time_taken = round(microtime(true) - $start_time, 3);

$description = "There are " . number_format((float)$db_types_total) . " different " 
	. mb_strtolower($att_desc[$att_for_calc]) 
	. "s in the parlink database for this query (" 
	. $query_record->print_solution_heading(NULL, false) . ') ' 
	. format_time_string($time_taken, $is_new_db)
	;



// if ($download_mode)
// {
// 	collocation_write_download( $att_for_calc, 
// 								$calc_stat, 
// 								$att_desc[$att_for_calc],
// 								$desc_of_basis, 
// 								$statistic[$calc_stat]['desc'], 
// 								$description, $result
// 								);
// }
// else
// {

	/* ------------------------------------------------ *
	 * create the HTML for the control panel at the top *
	 * ------------------------------------------------ */
	
	echo print_html_header("{$Corpus->title} -- CQPweb collocation results", $Config->css_path, array('cword'));
?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="7">
				<?php echo $description; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center">No.</td>
			<td class="concordgrey" align="center">Word</td>
			<td class="concordgrey" align="center">Total no. in <?php echo $desc_of_basis; ?></td>
			<td class="concordgrey" align="center">Expected parallel link frequency</td>
			<td class="concordgrey" align="center">Observed parallel link frequency</td>
			<td class="concordgrey" align="center">In no. of texts</td>
			<td class="concordgrey" align="center">Dice coefficient</td>
		</tr>
		
	<?php
	
	
	/* we'll always have a link to filter-by-collocation postprocess. These are the non-varying parameters. */
	$postp_url_parameters = print_url_params([
			'qname' => $qname,
			'newPostP' => 'coll',
			'newPostP_collocDB' => $dbname,
			'newPostP_collocDistFrom' => $calc_range_begin,
			'newPostP_collocDistTo' => $calc_range_end,
			'newPostP_collocAtt' => $att_for_calc,
			'newPostP_collocTagFilter' => $tag_filter,
	]);

	/* Likewise every row will use a link to the SOLOFORM UI. These are the non-varying parameters, 
	 * also used for the navlinks */
	$url_parameters_array = [
		'qname' => $qname,
		'collocCalcAtt' => $att_for_calc,
		'collocCalcStat' => $calc_stat,
		'maxCollocSpan' => $colloc_range,
		'collocCalcBegin' => $calc_range_begin,
		'collocCalcEnd' => $calc_range_end,
		'collocMinfreqTogether' => $calc_minfreq_together,
		'collocMinfreqColloc' => $calc_minfreq_collocalone,
	];
	foreach($colloc_atts_list as $p)
		$url_parameters_array["collAtt_$p"] = 1;
	if (!empty($tag_filter) && $Corpus->primary_annotation != $att_for_calc)
		$url_parameters_array['collocTagFilter'] = $tag_filter;
// 	if (0 < $begin_at)
// 		$url_parameters_array['beginAt'] = $begin_at;
// don't need begin at to go to SOLO or to a conc

	$url_parameters = print_url_params($url_parameters_array);

// create arrays to store the tokens and dice coefficient
	$palavras = [];
	$numero = [];
	$combinado = array();


	$i = $begin_at;

	while ($row = mysqli_fetch_assoc($result))
	{
		$i++;

		/* adjust number formatting : expected -> 3dp, significance -> 4dp, freq-> thousands*/
		if ( empty($row['significance']) )
			$row['significance'] = 'n/a';
		else
			// $row['significance'] = round($row['significance'], $statistic[$calc_stat]['decimal_places']);
			$row['significance'] = round($row['significance'], 4);
		$row['observed'] = number_format((float)$row['observed']);
		$row['expected'] = round($row['expected'], 3);
		$row['freq'] = number_format((float)$row['freq']);
		
		$att_for_calc_tt_show = strtr($row[$att_for_calc], array("'"=>"\'", '"'=>'&quot;'));
		
		$solo = "<a id=\"sololink:$i\" class=\"hasToolTip\" href=\"collocation.php?collocSolo=" . urlencode($row[$att_for_calc]) . '&'
			. $url_parameters
			. "\" data-tooltip=\"Show detailed info on <strong>$att_for_calc_tt_show</strong>\">{$row[$att_for_calc]}</a>"
			;
		
		$solo2 = urlencode($row[$att_for_calc]);

		$link = "<a id=\"postplink:$i\" class=\"hasToolTip\" href=\"concordance.php?$postp_url_parameters&newPostP_collocTarget=".urlencode($row[$att_for_calc])
			. "\" data-tooltip=\"Show solutions collocating with <strong>$att_for_calc_tt_show</strong>\">{$row['observed']}</a>"
			;
		
		$sigcell = ($calc_stat == COLLSTAT_RANK_FREQ ? '' : "<td class=\"concordgeneral\" align=\"center\">{$row['significance']}</td>");
		
		echo <<<END_OF_ROW_HTML

			<tr>
				<td class="concordgeneral" align="center">$i</td>
				<td class="concordgeneral" align="center">$solo</td>
				<td class="concordgeneral" align="center">{$row['freq']}</td>
				<td class="concordgeneral" align="center">{$row['expected']}</td>
				<td class="concordgeneral" align="center">$link</td>
				<td class="concordgeneral" align="center">{$row['text_id_count']}</td>
				$sigcell
			</tr>

END_OF_ROW_HTML;



// populate array with token and dice coefficient
$combinado[$solo2] = $row['significance'];

	}

// print_r($combinado);	
$comJSON = json_encode($combinado);
print_r($comJSON);
	
	?>
	
	</table>
	
	<?php





	echo print_html_footer('collocation');


// } /* endof "else" for "if $download_mode" */



cqpweb_shutdown_environment();

/* and we're done! */


/*
 * =============
 * END OF SCRIPT
 * =============
 */




function run_script_for_solo_collocation($query_record, $calc_options)
{
	/* note, this function is really just a moved-out-of-the-way chunk of the script */
	global $Config;
	global $Corpus;
	
	/* Globalling-in all the variables of the Collocations script. */
	$tag_filter = NULL;
	$calc_range_begin = NULL;
	$calc_range_end = NULL;
	$att_for_calc = NULL;
	$dbname = NULL;
	$soloform = NULL;
	
	extract($calc_options);
	
	/* bdo tags ensure that l-to-r goes back to normal after an Arabic (etc) string */
	$bdo_tag1 = ($Corpus->main_script_is_r2l ? '<bdo dir="ltr">' : '');
	$bdo_tag2 = ($Corpus->main_script_is_r2l ? '</bdo>' : '');
	
	$soloform_sql  = escape_sql($soloform);
	$soloform_html = escape_html($soloform);

	$statistic = load_statistic_info();
	
	foreach ($statistic as $s => $info)
	{
		$sql = create_parlinkstats_sql_query($calc_options);
		$counts = mysqli_fetch_assoc(do_sql_query($sql));
		
		/* adjust number formatting : expected -> 3dp, significance -> 4dp, freq-> thousands*/
		if ( ! isset($counts['significance']) )
			$statistic[$s]['value'] = 'n/a';
		else
			$statistic[$s]['value'] = round($counts['significance'], $statistic[$s]['decimal_places']);
	}
	
	/* this lot don't need doing on every iteration; they pick up their values from the final iteration of the above loop */
	$observed_to_show = number_format((float)$counts['observed']);
	$observed_for_calc = $counts['observed'];
	$expected_to_show = round($counts['expected'], 3);
	$basis_to_show = number_format((float)$counts['freq']);
	$number_of_texts_to_show = number_format((float)$counts['text_id_count']);
	
	if (QueryScope::TYPE_WHOLE_CORPUS == $query_record->qscope->type)
		$basis_point = 'the whole corpus';
	else
		$basis_point = 'the current subcorpus';
	
	echo print_html_header($Corpus->title . ' -- CQPweb collocation results', $Config->css_path);
	
	?>

	<table class="concordtable fullwidth">
		
	<?php
	/* check that soloform actually occurs at all */
	if (empty($basis_to_show))
	{
		echo '<tr><td class="concordgeneral"><strong>'
			, "<em>$soloform_html</em> does not collocate with &ldquo;"
	 		, escape_html($query_record->cqp_query)
			, "&rdquo; within a window of $calc_range_begin to $calc_range_end."
			, '</strong></td></tr></table>'
 			;
		return;
	}
	
	$tag_description = (empty($tag_filter) ? '' : " with tag restriction <em>$tag_filter</em>");
	
	echo '<tr><th class="concordtable" colspan="2">'
		, "Collocation information for node resulting from query &ldquo;"
 		, escape_html($query_record->cqp_query)
		, "&rdquo; collocating with &ldquo;$soloform_html&rdquo; $tag_description $bdo_tag1($basis_to_show occurrences in $basis_point)$bdo_tag2 "
 		, "</th></tr>\n"
 		, <<<END_ROW_HTML

		<tr>
			<th class=\"concordtable\" width=\"50%\">Type of statistic</th>
			<th class=\"concordtable\" width=\"50%\">
				Value (for window span $calc_range_begin to $calc_range_end)
			</th>
		</tr>

END_ROW_HTML;
	
	
	foreach ($statistic as $s => $info)
	{
		/* skip "rank by frequency" */
		if ($s == 0)
			continue;
		
		echo <<<END_ROW_HTML

			<tr>
				<td class=\"concordgrey\">{$info['desc']}</td>
				<td class=\"concordgeneral\" align=\"center\">{$info['value']}</td>
			</tr>

END_ROW_HTML;
	}
	echo "\n\t</table>\n", '<table class="concordtable fullwidth">', "\n";
	
	echo <<<END_ROW_HTML
	
		<tr>
			<th class="concordtable" colspan="4">
				Within the window $calc_range_begin to $calc_range_end, <em>$soloform_html</em> occurs
				$observed_to_show times as collocate in 
				$number_of_texts_to_show different texts 
				(expected frequency: $expected_to_show)
			</th>
		</tr>
		<tr>
			<th class="concordtable" align="left">Distance</th>
			<th class="concordtable">No. of occurrences</th>
			<th class="concordtable">In no. of texts</th>
			<th class="concordtable">Percent</th>
		</tr>

END_ROW_HTML;
	
	for ($i = $calc_range_begin ; $i <= $calc_range_end ; $i++)
	{
		if ($i == 0)
		{
			echo '<tr><td colspan="4"></td></tr>';
			continue;
		}

		$tag_clause = colloc_tagclause_from_filter($dbname, $att_for_calc, $Corpus->primary_annotation, $tag_filter);

		$sql = "SELECT count(`$att_for_calc`) as n_hits, count(distinct(text_id)) as n_texts
			FROM $dbname
			WHERE `$att_for_calc` = '$soloform_sql'
			$tag_clause
			AND `dist` = $i
			";

		$counts = mysqli_fetch_object(do_sql_query($sql));

		if ($counts->n_hits == 0)
		{
			$link   = $i;
			$n_hits  = 0;
			$n_texts = 0;
			$percent = 0;
		}
		else
		{
			$link = "<a id=\"concFor:$i\" class=\"hasToolTip\" href=\"concordance.php?qname={$query_record->qname}&newPostP=coll"
				. "&newPostP_collocDB=$dbname&newPostP_collocDistFrom=$i&newPostP_collocDistTo=$i"
				. "&newPostP_collocAtt=$att_for_calc&newPostP_collocTarget="
				. urlencode($soloform)
				. "&newPostP_collocTagFilter="
				. urlencode($tag_filter)
				. "\" data-tooltip=\"Show solutions collocating with <strong>$soloform_html</strong> at position <strong>$i</strong>\">$i</a>"
				;
			$n_hits  = number_format($counts->n_hits);
			$n_texts = number_format($counts->n_texts);
			$percent = round(($counts->n_hits/$observed_for_calc)*100.0, 1);
		}
		echo <<<END_ROW_HTML

			<tr>
				<td class="concordgrey">$link</td>
				<td class="concordgeneral" align="center">$n_hits</td>
				<td class="concordgeneral" align="center">$n_texts</td>
				<td class="concordgeneral" align="center">$percent%</td>
			</tr>

END_ROW_HTML;
	}
	
	?>
	
	</table>
	
	<?php
}



