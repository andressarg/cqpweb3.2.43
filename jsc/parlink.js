/**
* Parlink
* Copyright (C) 2020 Andressa Rodrigues Gomide
*
* This file contains javascript code to open a table with parallel links and scores
* to a new table
*/




/* this function get the div with the parlink table and opens it in a new page */
function open_parlink_table() {  
    var content = document.getElementById("parlink_pop_table");
    var nw = window.open("", "_blank", "toolbar=yes,scrollbars=yes,resizable=yes,top=100,left=500,width=600,height=400");
    nw.document.write(content.innerHTML);
    nw.document.close();
  
  }

