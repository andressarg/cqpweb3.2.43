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
 * @file This file contains some corpus-level data visualisation things.
 * 
 * HOW IT WORKS: 
 * 
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
//require('../lib/ceql.inc.php');
require('../lib/freqtable-lib.php');
require('../lib/metadata-lib.php');
require('../lib/annotation-lib.php');
require('../lib/corpus-lib.php');
require('../lib/concordance-lib.php');
//require('../lib/colloc-lib.inc.php');
//require('../lib/xml.inc.php');
//require('../lib/multivariate.inc.php');
require('../lib/lgcurve-lib.php');
require("../lib/postprocess-lib.php");
require("../lib/scope-lib.php");
require("../lib/cache-lib.php");
require("../lib/xml-lib.php");
require("../lib/distribution-lib.php");
require("../lib/query-forms.php");
// require("../lib/cwb.inc.php"); 

/* declare global variables */
$Corpus = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);

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



// if (isset($_GET['just']))
if (isset($_GET['just']) && $_GET['just'])
{

  // commented out for test

  // echo_tsv_dist($_GET['just']);   
  // echo "fimdetexto";
  // echo_tsv_hitposition($_GET['just']);
  // echo "fimdeposicao";
  // cqpweb_shutdown_environment();

  //comment out (acima) for test

  // teste abaixo
  echo_tsv_dist($dist_info);   
  echo "fimdetexto";
  echo_tsv_hitposition($dist_info);
  echo "fimdeposicao";

	cqpweb_shutdown_environment();
	
  // fim de teste



}


/* time now to set up the page! */
echo print_html_header(strip_tags($Corpus->title . ' &ndash; Dispersion'), $Config->css_path);

?>

  <!-- todo add d3 to CQPweb's local jQuery -->
  <!-- <script src="../jsc/d3.v4.min.js"></script> -->
  <script src="https://d3js.org/d3.v4.min.js"></script>
  <script src="../jsc/jquery.js"></script>
  <!-- Palmer D3-tip (2013) adapted by Gavrilete (2016) for D3v4 -->
  <script src="../jsc/d3-tip.js"></script>
  <script src="../jsc/saveSvgasPng.js"></script>  




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

.dropdown {
  position: relative;
  display: inline-block;
}

.dropdown-content {
  display: none;
  position: absolute;
  background-color: #f1f1f1;
  min-width: 160px;
  overflow: auto;
  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
  z-index: 1;
}

.dropdown-content a {
  color: black;
  padding: 12px 16px;
  text-decoration: none;
  display: block;
}

.dropdown a:hover {background-color: #ddd;}

.show {display: block;}

  </style>

<!-- table with dispersion measures (to be added to the legend) -->
  <div id="myDropdown" class="dropdown-content">
    <table>
      <tr>
        <th>DPnorm</th>
        <th>Juilland's D</th>
        <th>Range</th>
      </tr>
      <tr>
        <td id="dpsts"></td>
        <td id="jlsts"></td>
        <td id="rgsts"></td>
      </tr>
    </table>
  </div>



  <table class="concordtable" width="100%">
    <tr>
      <th colspan="8" class="concordtable"><?php echo $Corpus->title, ": Dispersion" ?></th>
    </tr>
    <tr>
      <!-- heading with dispersion measures -->
      <th colspan="8" class="concordtable">
        Your query "<span id="querystrings"></span>" returned <span id="freqcorpus"></span> matches in <span id="cospussize"></span> words (relative frequency: <span id="totrelfreq"></span> instances per million words)
        <br>DPnorm: <span id="dpnorm"></span> | Juilland's D: <span id="juilld"></span> | Range: <span id="range"></span> out of <span id="textsn"></span> texts
      </th> 
    </tr>
  </table>
  <table width="100%">
    <tr>
      <td>
        <?php
        echo print_newquery_box
        (
          isset($_GET['insertString'])    ? $_GET['insertString']    : NULL,
          isset($_GET['insertType'])      ? $_GET['insertType']      : NULL,
          isset($_GET['insertSubcorpus']) ? $_GET['insertSubcorpus'] : NULL,
          true
        );
        ?>
      </td>

    </tr>
  </table>
  <table class="concordtable" width="100%">
    <tr>
      <td class="concordgrey" nowrap="nowrap">Dispersion Overview</td>
    

    </tr>
    <tr>
      <td class="concordgeneral" align="left">
        <div id="overview" style="background-color: white"></div>
      </td>
      
    </tr>
    
    <tr>
      <td class="concordgrey" nowrap="nowrap">Single-text View</td>
    </tr>

    <tr>
      <td class="concordgeneral" align="left">
        <div id="singleView" style="background-color: white"></div>
      </td>
    </tr>
   
  </table>



  <script>
  // get text begining and end position and size, and query absolute and relative frequency per text
  var tsv_dist_data = `<?php echo_tsv_dist($dist_info);       ?>`;

  // get hits position and text where they occur
  var tsv_hitposition = `<?php echo_tsv_hitposition($dist_info); ?>`;

  // get all text names to add to x-axis
  var textosnames = `<?php text_names(); ?>`;

  // get query name
  var querystrg = `<?php echo_query(); ?>`;


  </script>



  <script>
  "use strict";


  // store the query string
  var querystrings = [querystrg];

  document.getElementById("querystrings").innerHTML = querystrings;

/* --------------------
Calculating Dispersions
--------------------- */

//functions to be used for calculations

  //divides an array by a divider
  function divideBy(array, divider)
  {
    var x = []
    for(var i=0; i < array.length; i++) 
    {
    x.push(array[i] / divider);
    }
    return x;
  };

  //multiply an array by a value
  function times(array, value)
  {
    var x = []
    for(var i=0; i < array.length; i++)
    {
      x.push(array[i] * value);
    }
    return x;
  };

  //calculate the difference between two arrays of the same size
  function diffArrays(array1, array2)
  {
    var x = [];
    for(var i = 0; i < array1.length; i++)
    {
    x.push(array1[i] - array2[i]);
    }
    return x;
  };

  //calculate the sum of absolute values in an array
  function sumAbs(array)
  {
    var x = 0;
    for(var i = 0; i < array.length; i++) 
    {
      x += Math.abs(array[i]);
    };
    return x;
  }

  //calculate mean of array
  function meanArray(array)
  {
    let sum = array.reduce((previous, current) => current += previous);
    let avg = sum / array.length;
    return avg;
  };

  // standard deviation 
  function standardDeviation(values)
  {
    var avg = meanArray(values);
    
    var squareDiffs = values.map(function(value)
    {
      var diff = value - avg;
      var sqrDiff = diff * diff;
      return sqrDiff;
    });
    
    var avgSquareDiff = meanArray(squareDiffs);

    var stdDev = Math.sqrt(avgSquareDiff);
    return stdDev;
  };

  //sd.pop
  function sdpop(array)
  {
    var std = standardDeviation(array) * Math.sqrt((array.length - 1) / array.length);
    return std;
  }


 // load data with text distribution
  let dataDist = d3.tsvParse(tsv_dist_data);

    dataDist.forEach(function(d){
      d.freqpm = +d.freqpm;
      d.absfreq = +d.absfreq;
      d.textsize = +d.textsize;
      d.TxtBegin = +d.TxtBegin;
      d.TxtEnd = +d.TxtEnd;
      d.qstg = querystrings[0];
    });

  // load data with text names and sizes 
  let txtNames = d3.tsvParse(textosnames);
  txtNames.forEach(function(d){
    d.textsize = +d.textsize;
    });


  // get some corpus info
  //array with sizes of texts in (absolute and percentage) 
  var textsize1 = txtNames.map(function(value) {
    return value.textsize;
  });
  var corpussize = sumAbs(textsize1);
  var corsizePer = divideBy(textsize1, corpussize);


  //create a marged array of text names and hits per text
  let mergedTxt = [];

  for(let i=0; i<txtNames.length; i++) {
    mergedTxt.push({
     ...txtNames[i], 
     ...(dataDist.find((itmInner) => itmInner.Text === txtNames[i].Text))}
    );
  };

  // frequency in each text (array) 
  var freqtext = mergedTxt.map(function(value) {  
    return value.absfreq;
  });

  freqtext = freqtext.map(v => v === undefined ? 0 : v); //get rid of undefined


  //frequency in corpus
  var freqcorpus = sumAbs(freqtext);
  document.getElementById("freqcorpus").innerHTML = freqcorpus; //return hits in corpus to result heading
  document.getElementById("cospussize").innerHTML = corpussize; // display corpus size in result heading
  document.getElementById("totrelfreq").innerHTML = Number(freqcorpus / corpussize * 1000000).toFixed(2); //display relative frequency


  // calculate DP and DPnorm
  var dpmeasure = sumAbs(diffArrays(divideBy(freqtext, freqcorpus), corsizePer))/2;
  var dpnorm = (dpmeasure/(1 - Math.min.apply(null, corsizePer)));
  document.getElementById("dpnorm").innerHTML = Number(dpnorm).toFixed(2);
  // store DPnorm in an array
  var dparray = [dpnorm];

  // calculate Juilland's D
  var propElemPar = divideBy(freqtext, freqcorpus)
  var juilld = 1 - (sdpop(propElemPar)/meanArray(propElemPar))/Math.sqrt(freqtext.length-1);
  document.getElementById("juilld").innerHTML = Number(juilld).toFixed(2);
  // store juilland'd in an array
  var jdarray = [juilld];

  // Range
  document.getElementById("textsn").innerHTML = freqtext.length; //display number of texts in corpus

  var sumcon = 0;
  for(var i=0; i < freqtext.length; i++)
  {
    if (freqtext[i] > 0)
    {
      sumcon += 1;
    }
  };

 document.getElementById("range").innerHTML = sumcon; // display range
 var rgarray = [sumcon];



/* ------------------
 dispersion overview
------------------ */


  // set the margings for main plot and brush view
  let marginOverview = {top: 20, right: 40, bottom: 40, left: 40}; 

  // set SVG size for all vis (including legend) 
  let widthSVG1 = 1200;
  let heightSVG2 = 800;

  // set SVG size for main plot
  let widthOverview = 980 - marginOverview.left - marginOverview.right;
  let heightOverview = 500 - marginOverview.top - marginOverview.bottom;

  // read file names
  let textXaxis = d3.tsvParse(textosnames); 
    textXaxis.forEach(function(d){
      d.Text = d.Text;
    });

  // set scales
  let xOverview = d3.scalePoint().range([0, widthOverview], 0.01); 
  let yOverview = d3.scaleLinear().range([heightOverview, 0]);

 // set the domains
    xOverview.domain(textXaxis.map(function(d) { return d.Text; }));
    yOverview.domain([0, d3.max(dataDist, function(d) { return d.freqpm; })]);


var reducetick = parseInt(freqtext.length / 65);

  // set X and Y axis
  let xAxisOverview = d3.axisBottom()
    .scale(xOverview)
     .tickValues(xOverview.domain().filter(function(d, i) { return !(i % reducetick); }));

  let yAxisOverview = d3.axisLeft()
    .scale(yOverview);



// ##########################################################setting this up now cos we'll def neeed it later. 
function convert_text_tick_index_to_id(j)
{
  return txtNames[j-1];
  // tick @ 1 returns text_id @ 0.
}
function convert_text_id_to_tick_index(id)
{
  // this is gonna be a super bad way todo it, look at all the iterating.
  // but I don't know how to construct a hash, alas!
  //txtNames[i].Text
  if (undefined === id || '' === id)
    return 0;
  for (var i = 0 ; i < txtNames.length ; i++)
    if (id == txtNames[i])
      return i = 1;
  return undefined; 
}



 


  // set the color scheme to be used as different queries are plotted
  let colordots = d3.scaleOrdinal(d3.schemeCategory10);
  
  // append svg for plot
  // let svgOverview = d3.select("#overview").append("svg")
  //     .attr("class","overview")
  //     .attr("id", "svgOverview")
  //     .attr("width", widthSVG1)
  //     .attr("height", heightSVG2)
  //     .append("g")
  //     .attr("transform", "translate(" + marginOverview.left + "," + marginOverview.top + ")");

// append svg for plot
  let svgOverview = d3.select("#overview").append("svg")
      .attr("class","overview")
      .attr("id", "svgOverview")
      .attr("width", widthSVG1)
      .attr("height", heightSVG2)
      .append("g")
    .attr("id", "topContainer")
        .attr("transform", "translate(" + marginOverview.left + "," + marginOverview.top + ")");





  // elements for the main chart
    // y label
    svgOverview.append('text')
    .attr('x', 10)
    .attr('y', 10)
    .attr('class', 'label')
    .text('Freq per 1,000,000');

    // x label
    svgOverview.append('text')
    .attr('x', widthOverview)
    .attr('y', heightOverview - 10)
    .attr('text-anchor', 'end')
    .attr('class', 'label')
    .text('Texts');

    // x-axis
    svgOverview.append('g') 
      .attr('transform', 'translate(0,' + heightOverview + ')')
      .attr('class', 'x axis')
      .call(xAxisOverview)
      .selectAll("text")
      .attr("y", 0)
      .attr("x", 9)
      .attr("dy", ".35em")
      .attr("transform", "rotate(90)")
      .style("text-anchor", "start");

  // y-axis
    svgOverview.append('g')
      .attr('transform', 'translate(0,0)')
      .attr('class', 'y-axis')
      .call(yAxisOverview);



  //tooltp for hovering
  let tool_tip = d3.tip()
    .attr("class", "d3-tip")
    .offset([-8, 0])
    .html(function(d) { return "query: " + d.qstg + "<br>" + "Text: " + d.Text + "<br>" + "Rel. Freq.: " + d.freqpm + "<br>" + "Abs. Freq.: " + d.absfreq; });

    svgOverview.call(tool_tip);


  // create variable to use for clicked dots and get the text and query
  var textkd;

  var querykd;


  //create inner group to apply zoom later
//   var svgInner = svgOverview.append("g");

//   //add circles for each text
//       svgInner.selectAll("circle")
//        .attr('x', widthOverview)  
//        .attr('y', heightOverview - 10) 
//        .data(dataDist)
//        .enter().append("circle")
//        .attr("class", "dot")
//        .attr("r", 5)
//        .attr("cx", function(d) { return xOverview(d.Text); }) 
//         // .attr("cx", function(d) { return convert_text_id_to_tick_index(xOverview(d.Text)); })
//        .attr("cy", function(d) { return yOverview(d.freqpm); })
//        .attr("fill", function(d) { return colordots(d.qstg); }) 
//        .style("fill-opacity", 0.6)
//        .on('mouseover', tool_tip.show)
//        .on('mouseout', tool_tip.hide)
//        .on("click", buttonClick)
// .on("wheel.zoom", function (d) { 
// console.log("about to dispatch wheel from " + d.Text);
// d3.select("#topContainer").dispatch("wheel.zoom"); 
// console.log("wheel sent!!!!");
// return false; 
// });
   
// console.log("dispatch successfully added");

// // put the zoom action on the G that contains everything else 
// var zoom = d3.zoom().on("zoom", zoomed1);
// d3.select("#topContainer").call(zoom);

// console.log("zoom action created and added to topContainer");

// //todo: edit zoom to update axis --> new zoomed1 should accomplish this

//     function zoomed1() {
// console.log("entering zoom1");
// //################
// // chunk re-copied from the gallery example 
//     // recover the new scale
//     var newX = d3.event.transform.rescaleX(xOverview);
//     var newY = d3.event.transform.rescaleY(yOverview);

//     // update axes with these new boundaries
//     xAxisOverview.call(d3.axisBottom(newX));
//     yAxisOverview.call(d3.axisLeft(newY));

// console.log("scale adjustments done");
// //#################

// // transform the dots,. AGAIN INVOLVES RE-COPYING
// //     svgInner.attr("transform", d3.event.transform)  
// svgInner.selectAll("circle")
//       .attr('cx', function(d) {return newX(d.Text)} )
//       .attr('cy', function(d) {return newY(d.freqpm)} );

// console.log("circle adjustments done");
//     };
// // end of zoomed1



  //create inner group to apply zoom later
  var svgInner = svgOverview.append("g");

  //add circles for each text
      svgInner.selectAll("circle")
       .attr('x', widthOverview)  
       .attr('y', heightOverview - 10) 
       .data(dataDist)
       .enter().append("circle")
       .attr("class", "dot")
       .attr("r", 5)
       .attr("cx", function(d) { return xOverview(d.Text); })   
       .attr("cy", function(d) { return yOverview(d.freqpm); })
       .attr("fill", function(d) { return colordots(d.qstg); }) 
       .style("fill-opacity", 0.6)
       .on('mouseover', tool_tip.show)
       .on('mouseout', tool_tip.hide)
       .on("click", buttonClick);
   

    svgInner.call(d3.zoom().on("zoom", zoomed1)); //todo: edit zoom to update axis

    function zoomed1() {
     svgInner.attr("transform", d3.event.transform)  
    };


    //load hit position
    var dataPstn = d3.tsvParse(tsv_hitposition);

      //coerce strings to number
      dataPstn.forEach(function(d)
      {
        d.position = +d.position
        d.qstg = querystrings[0]; 
      });

    var dataPstn2 = d3.tsvParse(tsv_hitposition);



/* -------------------
 text dispersion view 
------------------- */

  //plot text dispersion for the clicked text/dot
  function buttonClick()
  {  
    d3.select(this)
    .each(function(d)
    {
      textkd = d.Text;

      querykd = d.qstg;


      //filter clicked data
      var dataClicked = dataPstn.filter(function(d)
        { 
          return (d.Text == textkd && d.qstg == querykd)
        });

      //set margins, scales
      let margin = {top: 20, right: 20, bottom: 30, left: 40};

      let width = 960 - margin.left - margin.right;
      
      let height = 100 - margin.top - margin.bottom;

      let x = d3.scaleLinear()
                .range([0,width]);

      let y = d3.scaleLinear()
          .range([height, 0]);


      //set X and Y axis
      let xAxis = d3.axisBottom(x)
          .scale(x);

      let yAxis = d3.axisLeft()
          .scale(y);


      //svg and elements
      let svg = d3.selectAll("#singleView").append("svg:svg")
          .attr("class","singleView") 
          .data(dataClicked)
          .attr("width", width + margin.left + margin.right)
          .attr("height", height + margin.top + margin.bottom)
          // .call(d3.zoom().on("zoom", function ()
          // {
          //   svg.attr("transform", d3.event.transform) //TODO fix zoom
          // }))
          .append("g")
          .attr("transform", "translate(" + margin.left + "," + margin.top + ")");


      //filter dataDist to get each text length
      let textBegEnd = dataDist.filter(function(d)
          {
            return d.Text == textkd
          });


      // set domains for each text
        x.domain([d3.min(textBegEnd, function(d)
          {
            return d.TxtBegin;
          }), 
        d3.max(textBegEnd, function(d) 
          {
            return d.TxtEnd;
          })]);


      //append x-axis
        svg.append('g')
            .data(dataClicked)
            .attr('transform', 'translate(0,' + height + ')')
            .attr('class', 'x axis')
            .call(xAxis);

      //tool tip
      let tool_tip = d3.tip()
          .attr("class", "d3-tip")
          .offset([-8, 0])
          .html(function(d)
          {
            return "position: " + d.position; //TODO view KWIC instead
          });
        
        svg.call(tool_tip);

    
    // draw the cirlces
        svg.selectAll("circle")
            .data(dataClicked)
            .enter()
            .append("circle") 
            .attr("cx",function(d) {return x(d.position);})
            .attr("r", 5)
            .attr("fill", function(d) { return colordots(querystrings); })
            .attr("fill", function(d) { return colordots(d.qstg); }) 
            .style("fill-opacity", 0.3)
            .on('mouseover', tool_tip.show)
            .on('mouseout', tool_tip.hide);

    // barcode style
    // svg.selectAll("circle")
    //             .data(dataClicked)
    //             .enter()
    //             .append("rect") 
    //             .attr("x",function(d) {return x(d.position);})
    //             .attr("height", 25)
    //             .attr("width", 1)
    //             .attr("fill", function(d) { return colordots(querystrings); })
    //             .style("fill-opacity", 0.3)
    //             .on('mouseover', tool_tip.show)
    //             .on('mouseout', tool_tip.hide);


      // add query as a string
        svg.append('text')
        .data(dataClicked) //teste
          .attr('x', 10)
          .attr('y', height - 10)
          .attr('class', 'label')
          .text(function(d) {return querykd;});

      // add text name
        svg.append('text')
          .data(dataPstn)
          .attr('x', width)
          .attr('y', height - 10)
          .attr('text-anchor', 'end')
          .attr('class', 'label')
          .text(function(d) {return JSON.stringify(textkd);}); 

    }); 
  };



  // new actions from the top dropdown menu
  function newAction()
  {
    var x = document.getElementById("newAction").value;

    if (x == "newQuery") 
    {
      window.location.href = '../' + `<?php echo $Corpus->name ?>`; 
      return false;
    }
      else if (x == "saveimg") 
      {
      saveSvgAsPng(document.getElementById("svgOverview"), "dispersionplot.png"); //todo let user customize it (choose name, background color, size)
      return false;
      } 
      else 
      {
      //TODO add new rows to table as new queries are plotted
      var tab = window.open('about:blank', '_blank');
      tab.document.write(disMsrsTable);
      tab.document.close(); 
      }
  };



function makeTableHTML(query, dpnorm, juilland, range) 
{

    var result = 
    "<table id=`disTable` border=1 border-collapse=collapse>\
      <tr>\
        <th>query</th>\
        <th>DPnorm</th>\
        <th>Juilland</th>\
        <th>Range</th>\
      </tr>";

    for(var i=0; i<query.length; i++) 
    {
      result +=  "<tr><th>" + query[i] + "</th>" + "<th>" + dpnorm[i] + "</th><th>" + juilland[i] + "</th><th>" + range[i] + "</th></tr>";
    }

    result += "</table>";

    return result;

};



var disMsrsTable = makeTableHTML(querystrings, dparray, jdarray, rgarray);




  /* -------------
   add new query 
  ------------- */

  
  // create variables to store the data to come
  var next_qname;

  var newVal;

  var res;

  // create function to get new query
  function addData()
  {
    // newVal contains the new search pattern
    newVal = document.getElementById("theData").value;
    // call concordance.php to do the query and extract the query identifier from its return
    jQuery.get("concordance.php?theData=" + encodeURI(newVal) + "&qmode=sq_nocase",
      function(data){handle_new_qname(data);}, "text" );
    // store new values to qstrings
    querystrings.push(newVal);
    return false;
  };

  // create a function to catch the qname variable
  function handle_new_qname(str)
  {
    if (-1 != str.search(/Your query had no results/))
    {
      alert("No results in corpus for that query"); //todo if no results are found, break it
    }
    else
    {
      res = str.match(/<input type="hidden" name="qname" value="(\w+)"/);
      if (null == res)
      {
        console.log("error"); //todo add some kind of error action here
      }
      else
      {
      next_qname = res[1];

        // get the info to form the dispersion plot and table
        jQuery.get("dispersion.php?qname=" + next_qname + "&just=echo_tsv_dist",
          function(new_dist)
          {
            jQuery.get("dispersion.php?qname=" + next_qname + "&just=echo_tsv_hitposition",
              function(data){handle_new_data(new_dist, data);}, "text" );
          }, "text" );
      }
    }
  };



  //plot the new data to the graph 
  function handle_new_data(new_data)
  { 
    // read in only selected part
    var tsv_dist_data = new_data.match(/[\s\S]*?(?=fimdetexto)/i)[0];
    
    var tsv_hitposition = new_data.match(/(?=fimdetexto)([\s\S]*?)(?=fimdeposicao)/i)[1];
    console.log(tsv_dist_data);
    //load and parse corpus dispersion data             
    var dataPstn2 = d3.tsvParse(tsv_hitposition);

      //coerce strings to number
      dataPstn2.forEach(function(d)
      {
        d.Text = d.fimdetextoText;
        d.position = +d.position;
        d.qstg = querystrings[querystrings.length - 1];
      });

    dataPstn = dataPstn.concat(dataPstn2);

    // parse new data
    var new_dataDist = d3.tsvParse(tsv_dist_data);

      new_dataDist.forEach(function(d)
      {
        d.freqpm = +d.freqpm;
        d.absfreq = +d.absfreq;
        d.textsize = +d.textsize;
        d.TxtBegin = +d.TxtBegin;
        d.TxtEnd = +d.TxtEnd;
        d.qstg = querystrings[querystrings.length - 1];
      });

    // calculate dispersion here and push new values to it
      // create a marged array of text names and hits per text
    let mergedTxt2 = [];

        for(let i=0; i<txtNames.length; i++) {
          mergedTxt2.push({
           ...txtNames[i], 
           ...(new_dataDist.find((itmInner) => itmInner.Text === txtNames[i].Text))}
          );
        };
        //frequency in each text (array)  
        var freqtext2 = mergedTxt2.map(function(value) {  
          return value.absfreq;
        });

        freqtext2 = freqtext2.map(v => v === undefined ? 0 : v); //get rid of undefined


      //frequency in corpus
      var freqcorpus2 = sumAbs(freqtext2);

      // calculate DP and DPnorm
      var dpmeasure2 = sumAbs(diffArrays(divideBy(freqtext2, freqcorpus2), corsizePer))/2;
      var newDP = (dpmeasure2/(1 - Math.min.apply(null, corsizePer)));
       dparray.push(newDP);

      // calculate Juilland
      var propElemPar2 = divideBy(freqtext2, freqcorpus2)
      var juilld2 = 1 - (sdpop(propElemPar2)/meanArray(propElemPar2))/Math.sqrt(freqtext2.length-1);
        jdarray.push(juilld2);

      // Range
      var sumcon2 = 0;
      for(var i=0; i < freqtext2.length; i++)
      {
        if (freqtext2[i] > 0)
        {
          sumcon2 += 1;
        }
      };
      rgarray.push(sumcon2);

  
  //update table with dispersion measures
  disMsrsTable = makeTableHTML(querystrings, dparray, jdarray, rgarray);


      dataDist = dataDist.concat(new_dataDist);


      // y-axis doamin
      yOverview.domain([0, d3.max(dataDist, function(d) { return d.freqpm; })]);
      yAxisOverview.scale(yOverview); 

      svgOverview.selectAll("g.y-axis")
        .call(yAxisOverview);

     var svgInner2 = svgInner.selectAll(".dot")
                      .remove()
                      .exit()
                      .data(dataDist);
    
        svgInner2.enter()
                  .append("circle")
                  .attr("class", "dots2")
                  .attr("r", 5)
                  .attr("cx", function(d) { return xOverview(d.Text); })   
                  .attr("cy", function(d) { return yOverview(d.freqpm); })
                  .attr("fill", function(d) { return colordots(d.qstg); })
                  .style("fill-opacity", 0.6)
                  .on('mouseover', tool_tip.show)
                  .on('mouseout', tool_tip.hide)
                  .on("click", buttonClick);

      // add legend with query names
      let legendOverview = svgOverview.append("g")
        .attr('transform', "translate(" + widthOverview + "," + marginOverview.top + ")");

      // add legend
      legendOverview.selectAll("mydots")
        .data(querystrings)
        .enter()
        .append("circle")
        .attr("id", "dStats")
        .attr("class","dropbtn")
        .attr("cx", 100)
        .attr("cy", function(d,i){ return 100 + i*25}) // 100 is where the first dot appears. 25 is the distance between dots
        .attr("r", 7)
        .attr("fill", function(d) { return colordots(d)})
        .on("click", showDtable);

      legendOverview.selectAll("mylabels")
        .data(querystrings)
        .enter()
        .append("text")
        .attr("x", 120)
        .attr("y", function(d,i){ return 100 + i*25}) 
        .style("fill", function(d){ return colordots(d)})
        .text(function(d, i) {return d + " (" + Number(dparray[i]).toFixed(2) + ")"} )
        .attr("text-anchor", "left")
        .style("alignment-baseline", "middle");

      legendOverview.append("text")
        .text("Queries (DPnorm)")
        .attr("x", 90)
        .attr("y", 70)
        .attr("text-anchor", "left")
        .style("alignment-baseline", "middle");


    // search value in array by key
    function arraySearch(arr,val) 
    {
        for (var i=0; i<arr.length; i++)
            if (arr[i] === val)                    
                return i;
        return false;
      }





    // show dispersion table when clicking on legend dot
    function showDtable() 
    {
      d3.select(this)
      .each(function(d)
      {
        var a = arraySearch(querystrings, d);
        var x = $("circle").position();
        document.getElementById("myDropdown").style.left = 980 ;
        document.getElementById("myDropdown").style.top = 300 + a*21 ;
        document.getElementById("myDropdown").classList.toggle("show");

        var dpsts = Number(dparray[a]).toFixed(2);
        document.getElementById("dpsts").innerHTML = dpsts;

        var jlsts = Number(jdarray[a]).toFixed(2);
        document.getElementById("jlsts").innerHTML = jlsts;

        var rgsts = rgarray[a] + "/" + freqtext.length;
        document.getElementById("rgsts").innerHTML = rgsts;
      })
    };

    // Close the dropdown if the user clicks outside of it
    window.onclick = function(event) 
    {
      if (!event.target.matches('.dropbtn')) 
      {
        var dropdowns = document.getElementsByClassName("dropdown-content");
        var i;
        for (i = 0; i < dropdowns.length; i++) 
        {
          var openDropdown = dropdowns[i];
          if (openDropdown.classList.contains('show')) 
          {
            openDropdown.classList.remove('show');
          }
        }
      }
    };


  };









  </script>

<?php

echo print_html_footer(); 


cqpweb_shutdown_environment();


/**
 * Gets a string containing the three-column data table used by the D3 script 
 * to render the advanced dispersion plot (dispersion overview).
 */
// todo: clean everything below

// function echo_tsv_dist($query_record, $dist_info, $db_record, $dbname)
// function echo_tsv_dist()
function echo_tsv_dist(DistInfo $dist_info)
{

  global $User;
  
    
    /* create local vars to simplify SQL emmbedding... */
    $dbname                   = $dist_info->db_record['dbname'];
    $db_idfield               = $dist_info->db_idfield; 
    $join_field               = $dist_info->join_field;
    $join_ntoks               = $dist_info->join_ntoks;
    $join_table               = $dist_info->join_table;
    
  $sql = "SELECT db.`$db_idfield` as item_id, md.`$join_ntoks` as n_tokens, count(*) as hits, 
        md.`cqp_begin`as begin_pos, md.`cqp_end` as end_pos 
        FROM `$dbname` as db 
        LEFT JOIN `$join_table` as md 
        ON db.`$db_idfield` = md.`$join_field`
        GROUP BY db.`$db_idfield`
        ORDER BY db.`$db_idfield`";
      
      // $result = do_mysql_query($sql);
      $result = do_sql_query($sql);
      
        echo "Text\t"
          , "TxtBegin\t"
          , "TxtEnd\t"
          , "textsize\t"
          , "absfreq\t"
          , "freqpm\n"
          ;

      // while (false !== ($r = mysql_fetch_object($result)))
      while ($r = mysqli_fetch_object($result))
        echo $r->item_id, "\t", 0, "\t", $r->end_pos - $r->begin_pos, "\t", $r->n_tokens, "\t", $r->hits, "\t", round(($r->hits / $r->n_tokens) * 1000000, 2), "\n";


}


function text_names()
{

  global $User;
  global $Corpus;


$result = do_mysql_query("select text_id, words from text_metadata_for_{$Corpus->name}");


          echo "Text\t"
            ,   "textsize\n";

  // while (false !== ($r = mysql_fetch_object($result)))
  while ($r = mysqli_fetch_object($result))
          echo $r->text_id, "\t", $r->words, "\n";

}


//create tsv with hit position
function echo_tsv_hitposition(DistInfo $dist_info)
{

// $qname = safe_qname_from_get();

//   $query_record = QueryRecord::new_from_qname($qname);
//   if (false === $query_record)
//     exiterror_general("The specified query $qname was not found in cache!");

//   /* does a db for the distribution exist? */

//   /* search the db list for a db whose parameters match those of the query named as qname; if it doesn't exist, create one */

//   $db_record = check_dblist_parameters(new DbType(DB_TYPE_DIST), $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);

//   if (false === $db_record)
//   {
//     $dbname = create_db(new DbType(DB_TYPE_DIST), $qname, $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);
//     $db_record = check_dblist_dbname($dbname);
//   }
//   else
//   {
//     $dbname = $db_record['dbname'];
//     touch_db($dbname);
//   }


//   /* we tuck all the program info into a single object that can be passed as a unit. */
//   $dist_info = new DistInfo($_GET, $query_record);

//   $dist_info->append_db_record($db_record);
//   $dist_info->append_query_record($query_record);


   global $User;
  
    
    /* create local vars to simplify SQL emmbedding... */
    $dbname                   = $dist_info->db_record['dbname'];
    $db_idfield               = $dist_info->db_idfield; 
    $join_field               = $dist_info->join_field;
    $join_ntoks               = $dist_info->join_ntoks;
    $join_table               = $dist_info->join_table;



    
$sql2 = "SELECT db.`beginPosition` as beginPosition, db.`text_id`,  (  db.`beginPosition` - md.`cqp_begin` ) as pos_in_text
        FROM `$dbname` as db
        LEFT JOIN `$join_table` as md ON db.`$db_idfield` = md.`$join_field`
        ORDER BY db.`$db_idfield`";


    $result2 = do_mysql_query($sql2);
    
      echo "Text\t"
        , "position\n"
        ;



    // while (false !== ($r2 = mysql_fetch_object($result2)))
    while ($r2 = mysqli_fetch_object($result2))
      echo $r2->text_id, "\t", $r2->pos_in_text, "\n";





}




function echo_query()
{

$qname = safe_qname_from_get();

$query_record = QueryRecord::new_from_qname($qname);

$querystg = $query_record->simple_query;

echo $querystg;

}



function print_newquery_box($qstring, $qmode, $qsubcorpus, $show_mini_restrictions)
{
  global $Config;
  global $Corpus;
  global $User;
  
  
  /* GET VARIABLES READY: contents of query box */
  $qstring = ( ! empty($qstring) ? escape_html(prepare_query_string($qstring)) : '' );
  
  if ($Config->show_match_strategy_switcher)
  {
    if (preg_match('/^\(\?\s*(\w+)\s*\)\s*/', $qstring, $m))
    {
      if (in_array($m[1], array('traditional', 'shortest', 'longest')))
        $strategy_insert = $m[1];
      else if ('standard' == $m[1])
        $strategy_insert = '0';
      $qstring = preg_replace('/^'.preg_quote($m[0], '/').'/', '', $qstring);
    }
    else
      $strategy_insert = '0';
  }
  

  /* GET VARIABLES READY: the query mode. */
  $modemap = array(
    'cqp'       => 'CQP syntax',
    'sq_nocase' => 'Simple query (ignore case)',
    'sq_case'   => 'Simple query (case-sensitive)',
    );
  if (! array_key_exists($qmode, $modemap) )
    $qmode = ($Corpus->uses_case_sensitivity ? 'sq_case' : 'sq_nocase');
    /* includes NULL, empty */
  
  $mode_options = '';
  foreach ($modemap as $mode => $modedesc)
    $mode_options .= "\n\t\t\t\t\t\t\t<option value=\"$mode\"" . ($qmode == $mode ? ' selected="selected"' : '') . ">$modedesc</option>";

  
  /* GET VARIABLES READY: hidden attribute help */
  $style_display = ('cqp' != $qmode ? "display: none" : '');
  $mode_js       = ('cqp' != $qmode ? 'onChange="if ($(\'#qmode\').val()==\'cqp\') $(\'#searchBoxAttributeInfo\').slideDown();"' : '');
  

  //voltar aqui: tirei o abaixo
  // $p_atts = "\n";
  // foreach(get_corpus_annotation_info() as $p)
  // {
  //   $p->tagset = escape_html($p->tagset);
  //   $p->description = escape_html($p->description);
  //   $tagset = (empty($p->tagset) ? '' : "(using {$p->tagset})");
  //   $p_atts .= "\t\t\t<tr>\t<td><code>{$p->handle}</code></td>\t<td>{$p->description}$tagset</td>\t</tr>\n";
  // }
  
  $s_atts = "\n";
  foreach(list_xml_all($Corpus->name) as $s=>$s_desc)
    $s_atts .= "\t\t\t\t\t<tr>\t<td><code>&lt;{$s}&gt;</code></td>\t<td>" . escape_html($s_desc) . "</td>\t</tr>\n";
  if ($s_atts == "\n")
    $s_atts = "\n<tr>\t<td colspan='2'><code>None.</code></td>\t</tr>\n";

  /* and, while we do the a-atts, simultaneously,  GET VARIABLES READY: aligned corpus display */
  $a_atts = "\n";
  $align_options = '';
  foreach(check_alignment_permissions(list_corpus_alignments($Corpus->name)) as $a=>$a_desc)
  {
    $a_atts .= "\t\t\t\t\t<tr>\t<td><code>&lt;{$a}&gt;</code></td>\t<td>" . escape_html($a_desc) . "</td>\t</tr>\n";
    $align_options .= "\n\t\t\t\t\t\t\t<option value=\"$a\">Show text from parallel corpus &ldquo;" . escape_html($a_desc) . "&rdquo;</option>";
  }
  if ($a_atts == "\n")
    $a_atts = "\n<tr>\t<td colspan='2'><code>None.</code></td>\t</tr>\n";
  /* we do this for a-atts but not p/s-atts because there is always at least word and at least text/text_id */



  /* ASSEMBLE THE RESTRICTIONS MINI-CONTROL TOOL */
  if ( ! $show_mini_restrictions)
    $restrictions_html = '';
  else
  {
    /* create options for the Primary Classification */
    /* first option is always whole corpus */
    $restrict_options = "\n\t\t\t\t\t\t\t<option value=\"\"" 
      . ( empty($subcorpus) ? ' selected="selected"' : '' )
      . '>None (search whole corpus)</option>'
      ;
    
    $field = $Corpus->primary_classification_field;

    //voltaraqui : tirei o abaixo
    // foreach (metadata_category_listdescs($field, $Corpus->name) as $h => $c)
    //   $restrict_options .= "\n\t\t\t\t\t\t\t<option value=\"-|$field~$h\">".(empty($c) ? $h : escape_html($c))."</option>";
    
    /* list the user's subcorpora for this corpus, including the last set of restrictions used */
    
    $result = do_mysql_query("select * from saved_subcorpora where corpus = '{$Corpus->name}' and user = '{$User->username}' order by name");
    
    while (false !== ($sc = Subcorpus::new_from_db_result($result)))
    {
      if ($sc->name == '--last_restrictions')
        $restrict_options .= "\n\t\t\t\t\t\t\t<option value=\"--last_restrictions\">Last restrictions ("
          . $sc->print_size_tokens() . ' words in ' 
          . $sc->print_size_items()  . ')</option>'
          ;
      else
        $restrict_options .= "\n\t\t\t\t\t\t\t<option value=\"~sc~{$sc->id}\""
          . ($qsubcorpus == $sc->id ? ' selected="selected"' : '')
          . '>Subcorpus: ' . $sc->name . ' ('
          . $sc->print_size_tokens() . ' words in ' 
          . $sc->print_size_items()  . ')</option>'
          ;
    }
    
    /* we now have all the subcorpus/restrictions options, so assemble the HTML */
    $restrictions_html = <<<END_RESTRICT_ROW

        
          <td class="basicbox">Restriction:
          <input type="hidden" name="del" size="-1" value="begin" />
          
            <select name="t">
              $restrict_options
            </select>
          </td>
        
        <input type="hidden" name="del" size="-1" value="end" />

END_RESTRICT_ROW;

  } /* end of $show_mini_restrictions is true */


  /* ALL DONE: so assemble the HTML from the above variables && return it. */

  return <<<END_OF_HTML

    
          <td class="basicbox">
          <form onSubmit="return addData()">
          <input type="submit" id="newQueryForm" value="Add Query"/>
          <input type="text" id="theData" placeholder="add new query" >
          $qstring
          </form> 
            
          </td>
          <td class="basicbox">Query mode:
            <select id="qmode" name="qmode" $mode_js>
              $mode_options
            </select>

          </td>
        

        





        $restrictions_html

                

          <td nowrap="nowrap" class="concordgrey" align="center">
            <select id="newAction" onchange="newAction()">
              <option selected disabled>New action...</option>  
              <option value="newQuery">New query</option>
              <option value="saveimg">Save image</option>
              <option value="dispTable">Dispersion table</option>
            </select>
          </form>
          </td>
        
      

END_OF_HTML;

}