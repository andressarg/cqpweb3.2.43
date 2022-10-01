<?php
/*
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-today Andrew Hardie and contributors
 *
 * See http://cwb.sourceforge.net/cqpweb.php
 *
 * This file is part of CQPweb.
 *
 * CQPweb is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * CQPweb is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @file 
 *
 * This file contains the code to generate the dispersion interface.
 */

/* Allow for usr/xxxx/corpus: if we are 3 levels down instead of 2, move up two levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../exe');

require('../lib/environment.php');

/* include function library files */
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/html-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/sql-lib.php');
// require('../lib/sql-definitions.php');
require('../lib/db-lib.php');
require('../lib/cqp.php');
require('../lib/freqtable-lib.php');
require('../lib/metadata-lib.php');
require('../lib/annotation-lib.php');
require('../lib/corpus-lib.php');
require('../lib/concordance-lib.php');
//require("../lib/postprocess-lib.php");
require("../lib/scope-lib.php");
require("../lib/cache-lib.php");
require("../lib/xml-lib.php");
require("../lib/distribution-lib.php");
require("../lib/query-forms.php");

$Corpus = $Config = null;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);


if ($Config->hide_experimental_features)
	exiterror("Dispersion is an experimental feature; this server is configured to disable it.");

// /* start by getting the info we need: a QueryRecord and a Distribution datablob record. */

// $qid = safe_qid_from_get();

// if (!($query_record = QueryRecord::new_from_qid($qid)))
// 	exiterror("The specified query (# $qid) was not found in cache!");

// /* search the db list for a db whose parameters match those of the query record; if it doesn't exist, create one */
// $db_record = get_distribution_db_record($query_record);


// $dist_info = new DistInfo($_GET, $query_record, $db_record);


/* Global variables: the qname, the qrecord, the dbname, the dbrecord, and finally the distinfo (containing all info for this program run.) */


$qname = safe_qname_from_get();

$query_record = QueryRecord::new_from_qname($qname);
if (false === $query_record)
  exiterror("The specified query $qname was not found in cache!");


/* does a db for the distribution exist? */

/* search the db list for a db whose parameters match those of the query named as qname; if it doesn't exist, create one */

$db_record = check_dblist_parameters(new DbType(DB_TYPE_DIST), $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);

if (false === $db_record)
{
  $dbname = create_db(new DbType(DB_TYPE_DIST), $qname, $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);
  $db_record = check_dblist_dbname($dbname);
}
else
{
  $dbname = $db_record['dbname'];
  touch_db($dbname);
}


/* we tuck all the program info into a single object that can be passed as a unit. */
$dist_info = new DistInfo($_GET, $query_record, $db_record);



/* the "just" parameter means "just send me the data as TSV, no HTML/CSS/JS needed!" 
   Used by client to add more queries to the display. */
if (isset($_GET['just']) && $_GET['just'])
{
	$o = new stdClass();

	$o->tsv_dist = print_tsv_dist($dist_info);
	$o->tsv_hits = print_tsv_hitposition($dist_info);

	header('Content-Type: application/json');
	echo json_encode($o);

	cqpweb_shutdown_environment();
	exit;
}


/* time now to set up the page! */
// echo print_html_header(strip_tags($Corpus->title . ' &ndash; Dispersion'), $Config->colour_scheme, ['dispersion', 'array-maths']);
echo print_html_header(strip_tags($Corpus->title . ' &ndash; Dispersion'), $Config->css_path);

?>
	<!-- todo add d3 to CQPweb's local jsc -->
	<script defer src="https://d3js.org/d3.v4.js"></script>
	<!-- Palmer D3-tip (2013) adapted by Gavrilete (2016) for D3v4 -->
	<script defer src="../jsc/d3-tip.js"></script>
	<script defer src="../jsc/saveSvgasPng.js"></script>



	<!-- style for visualization-->
	<style>
		.axis path, .axis line {
			fill: none;
			stroke: #000;
			shape-rendering: crispEdges;
		}


		/*style for tip when hovering*/
		.d3-tip {
			line-height: 1;
			padding: 6px;
			background: rgba(0, 0, 0, 0.8);
			color: #fff;
			border-radius: 4px;
			font-size: 12px;
		}

		/* Creates a small triangle extender for the tooltip */
		.d3-tip:after {
			box-sizing: border-box;
			display: inline;
			font-size: 10px;
			width: 100%;
			line-height: 1;
			color: rgba(0, 0, 0, 0.8);
			content: "\25BC";
			position: absolute;
			text-align: center;
		}

		/* Style northward tooltips specifically */
		.d3-tip.n:after {
			margin: -2px 0 0 0;
			top: 100%;
			left: 0;
		}

/*style for dropdown*/
.dropbtn {
	background-color: #3498DB;
	color: white;
	padding: 16px;
	font-size: 16px;
	border: none;
	cursor: pointer;
}

.dropbtn:hover, .dropbtn:focus {
	background-color: #2980B9;
}

/*
.dropdown {
	position: relative;
	display: inline-block;
}*/

#qStatsPopup {
	display: none;
	position: absolute;
	background-color: #f1f1f1;
	min-width: 160px;
	overflow: auto;
	box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
	z-index: 1;
}

/*#qStatsPopup a {
	color: black;
	padding: 12px 16px;
	text-decoration: none;
	display: block;
}*/

/*
.dropdown a:hover {background-color: #ddd;}*/

.show {display: block;}

	</style>

<!-- table with dispersion measures (to be added to the legend) -->
	<div id="qStatsPopup">
		<table>
			<tr>
				<th>DPnorm</th>
				<th>Juilland&rsquo;s D</th>
				<th>Range</th>
			</tr>
			<tr>
				<td id="dpsts"></td>
				<td id="jlsts"></td>
				<td id="rgsts"></td>
			</tr>
		</table>
	</div>



	<table class="layout-frame fullwidth">
		<tr>
			<th class="layout-strong" colspan="4"><?php echo escape_html($Corpus->title), ": Dispersion" ?></th>
		</tr>
		<tr>
			<td class="layout-contrast" colspan="4">This tool is EXPERIMENTAL and may be out of working order. Please do not report bugs at this time.</td>
		</tr>
		<tr>
			<!-- heading with dispersion measures -->
			<th colspan="4" class="layout-strong">
				<?php echo $query_record->print_solution_heading(), "\n"; ?>
				<br>
				DPnorm: <span id="dpnorm"></span> 
				| 
				Juilland&rsquo;s D: <span id="juilld"></span> 
				| 
				Range: <span id="range"></span> out of <span id="textsn"></span> texts
			</th>
		</tr>
		<tr>
			<?php
			// $box = print_mini_search_box('addQueryForm');
			$box = str_replace('type="submit" value="Run Query">', 'type="submit" value="Add Query">', $box);
			$box = str_replace('placeholder="Enter query"', 'placeholder="Add query to dispersion display"', $box);
			echo $box, "\n";
			?>
			<td class="layout-bg algC nowrap">
				<form id="dispersionMainDropdown" class="autoSubmit" action="" method="get">
					<select class="actionSelect" name="menuChoice">
						<option disabled selected>Choose action...</option>
						<option value="newQuery">New query</option>
						<option value="saveImg">Save image</option>
						<option value="dispTable">Dispersion measures table</option>
					</select>
				</form>
			</td>
		</tr>
	</table>
	<table class="layout-frame fullwidth">
		<tr>
			<td class="layout-bg nowrap">Dispersion Overview</td>
		</tr>
		<tr>
			<td class="layout-fg algL">
				<div id="overview" style="background-color: white"></div>
			</td>
		</tr>
		<tr>
			<td class="layout-bg algL nowrap">Single-text View</td>
		</tr>
		<tr>
			<td class="layout-fg algL">
				<div id="singleTextView" style="background-color: white"></div>
			</td>
		</tr>
	</table>



	<script>


$( function () {


	/* disable UI until Overview is fully drawn */
	/* this, in thoery, oughtt to overlay the display during setup, then disappear. */
	// TODO it dunt work now
	greyout_and_throbber("plotting dispersion..."); // TODO does not render. need ot force re-render.
	d3.select("#svgOverview").transition().on("end", function() { alert("here!"); clog(this); greyout_off(); } );



	/*
	 * ===================
	 * PHP DATA INSERTIONS
	 * ===================
	 */
	
	// get text begining and end position and size, and query absolute and relative frequency per text
	var tsv_dist_data = `<?php echo print_tsv_dist($dist_info); ?>`;

	// get hits position and text where they occur
	var tsv_hitposition = `<?php echo print_tsv_hitposition($dist_info); ?>`;

	var init_query = `<?php echo $query_record->simple_query; ?>`;

	/* control variables (may later be inserted from PHP ?) */
	/* Size of the complete visualisation (including legend) in pixels */
	var width_of_viz_svg  = 1200;
	var height_of_viz_svg = 800;



	/* ------------------------------
	 * initialise the dispersion page
	 * ------------------------------ */



	/* ----------
	 * data setup
	 * ---------- */


	/* global container object for dispersion data */
	Dispersion = {width_of_viz_svg: width_of_viz_svg, height_of_viz_svg: height_of_viz_svg};




	
	/* Arrays to store query strings and dispersion measure results.
	 *
	 * querystrings Array of strings containing the search terms of the queries.
	 * dpnorm       Array of DP-norm values for those queries.
	 * juilland     Array of Juilland's D values for those queries.
	 * range        Array of range values for those queries.
	 * 
	 * All arrays must be of the same length, and the indexes are the same
	 * (i.e. dpnorm[3] is the DPnorm of the third query, whose search term
	 * is stored as querystrings[3])
	 */
	Dispersion.querystrings = [init_query];
	Dispersion.dpnorm       = [];
	Dispersion.juilland     = [];
	Dispersion.range        = [];
	/* strings containing the search terms */

	import_dispersion_data_from_tsv(tsv_dist_data, tsv_hitposition);
clog(1);
	/* for any JS engine not clever enough to realise these hunks of memory are done with */
	tsv_dist_data = tsv_hitposition = null;


	/* -------------------------
	 * dispersion overview setup
	 * ------------------------- */
	 
	initialise_dispersion_overview_visualisation();

	/* now setup is launched, and the svgOverview exists, schedule its greyout-off. */
// 	d3.select("#svgOverview").transition().on("end", greyout_off);
	d3.select("#svgOverview").transition().on("end", function () {clog("ending throb!"); greyout_off(); });
clog(2);

	update_dispersion_measure_table();
clog(3);

	force_x_ticks_to_text_id(Overview.x_axis, Overview.x_scale);
clog(4);


	// //create inner group to apply zoom later
	// Overview.zoom_group = Overview.svg_plot.append("g");
	
	// //add circles for each text
	//     Overview.dot_area
	//      .attr('x', Overview.width)
	//      .attr('y', Overview.height - 10)
	//      .data(d3_dist_data)
	//      .enter().append("circle")
	//      .attr("class", "dot")
	//      .attr("r", 5)
	//      .attr("cx", function(d) { return Overview.x_scale(d.Text); })
	//      .attr("cy", function(d) { return Overview.y_scale(d.freqpm); })
	//      .attr("fill", function(d) { return get_colour_scheme_for_overview(d.qstg); })
	//      .style("fill-opacity", 0.6)
	//      .on('mouseover', Dispersion.tool_tip.show)
	//      .on('mouseout', Dispersion.tool_tip.hide)
	//      .on("click", add_new_single_text_view_to_display);
	
	
// 	greyout_and_throbber("plotting dispersion..."); // TODO does not render. need ot force re-render.
	greyout_off();

}
); /* end of call to $() to make global code run when ready */


// }
// );
	</script>

<?php

echo print_html_footer();

cqpweb_shutdown_environment();

exit;

/*
 * END OF SCRIPT
 * =============
 * 
 * 
 * ... support functions follow.
 */

/**
 * Gets a string containing the seven-column data table used by the D3 script
 * to render the advanced dispersion plot (dispersion overview).
 */
function print_tsv_dist(DistInfo $dist_info)
{
	$db_table = compose_db_tablename($dist_info->db_record->id);
	
	/* create local vars to simplify SQL embedding... */
	$db_idfield               = $dist_info->db_idfield;
	$join_field               = $dist_info->join_field;
	$join_ntoks               = $dist_info->join_ntoks;
	$join_table               = $dist_info->join_table;
		
	/* We want a 0 entry for texts with n hits, so left join with the metadata table first.
	 * We then want to know how many hits, so count the number of text_id values *in the db*
	 * (just simply counting rows would lead to texts with 0 hits being counted as if they had one hit) 
	 */
	$sql = "SELECT md.`$db_idfield` as item_id, md.`$join_ntoks` as n_tokens, count(db.`$db_idfield`) as hits, 
				md.`cpos_begin` as begin_pos, md.`cpos_end` as end_pos
				FROM `$join_table` as md
				LEFT JOIN `$db_table` as db 
				ON db.`$db_idfield` = md.`$join_field`
				GROUP BY md.`$db_idfield`
				ORDER BY md.`$db_idfield`";
	
	$result = do_sql_query($sql);

	/* header row for the TSV */
	$string =  
		"Text\t"   // text_id
		. "TxtBegin\t"   // text_cpos_begin
		. "TxtEnd\t"   // text_cpos_end
		. "textsize\t" // text_n_tokens
		
		. "absfreq\t"   // text_n_hits_abs
		. "freqpm\t"    // text_n_hits_rel
		. "Text_n\n"    // text_sequence_n
		;

	$ix = 1;
	while ($r = mysqli_fetch_object($result))
		$string .= $r->item_id . "\t"                              /* text ID */
			. "0\t"                                                /* cpos offset (non-absolute) of beginning of the text */
			. ($r->end_pos - $r->begin_pos) . "\t"                 /* cpos offset (non-absolute) of end of the text */
			. $r->n_tokens . "\t"                                  /* N of tokens in text */
			. $r->hits . "\t"                                      /* N of hits in text */
			. round(($r->hits / $r->n_tokens) * 1000000, 2) . "\t" /* frequency per million words of the match in this text. */
			. $ix++ . "\n"                                         /* index to text ID */
			;
	return $string;
}


/**
 * Gets a string containing the three-column data table used by the D3 script
 * to render the advanced dispersion plot (by-text view).
 */
function print_tsv_hitposition($dist_info)
{
	$db_table = compose_db_tablename($dist_info->db_record->id);
	
	/* create local vars to simplify SQL emmbedding... */
	$db_idfield               = $dist_info->db_idfield;
	$join_field               = $dist_info->join_field;
	$join_table               = $dist_info->join_table;
	
	$sql = "SELECT  db.`$db_idfield`                     as item_id,
				( db.`cpos_begin` - md.`cpos_begin` ) as hit_delta_within_text,
				md.`cpos_begin`                          as text_cpos_begin
			FROM `$db_table` as db
			LEFT JOIN `$join_table` as md 
			ON db.`$db_idfield` = md.`$join_field`
			ORDER BY db.`$db_idfield`, db.`cpos_begin` asc";
	
	$result = do_sql_query($sql);
	
	$string = "Text\tposition\tstartpoint\n";
	
	while ($r = mysqli_fetch_object($result))
		$string .= $r->item_id . "\t" . $r->hit_delta_within_text . "\t" . $r->text_cpos_begin . "\n";
	
	return $string;
}


