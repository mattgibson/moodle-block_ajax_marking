.icon-course, .icon-assign, .icon-workshop, .icon-forum, .icon-quiz, .icon-question, .icon-journal, .icon-group {
  padding-left: 20px;
  padding-bottom: 3px;
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: #fff;
  white-space: nowrap;
  margin-left: 0px;
}
.icon-course {
  background-image: url(/theme/chameleon/pix/i/course.gif);
}
.icon-assign {
  background-image: url(/theme/chameleon/pix/mod/assignment/icon.gif);
}
.icon-workshop {
  background-image: url(/theme/chameleon/pix/mod/workshop/icon.gif);
}
.icon-forum {
  background-image: url(/theme/chameleon/pix/mod/forum/icon.gif);
}
.icon-quiz {
  background-image: url(/theme/chameleon/pix/mod/quiz/icon.gif);
}
.icon-question {
  background-image: url(/theme/chameleon/pix/i/questions.gif);
}
.icon-journal {
  background-image: url(/theme/chameleon/pix/mod/journal/icon.gif);
}
.icon-group {
  background-image: url(/theme/chameleon/pix/i/users.gif);
}

/* the following 8 styles give different coloured borders to 
   submissions depending on when they were submitted. The 
   colours may not be the best for your theme so change them
   below if needs be. The timings are in javascript.js ata round line
   340. If you have colour blind users, you may need to take contrast into account
   and maybe vary the line style - dotted, dashed, solid.
*/
   
.icon-user-one, .icon-user-two, .icon-user-three, .icon-user-four, .icon-user-five, .icon-user-six, .icon-user-seven, .icon-user-eight {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-style: none;
  border-width: 2px;
  overflow: hidden;
  max-width: 140px;
  margin: 0;
}
.icon-user-one {
 
  background-color: #ccffcc; 
}
.icon-user-two  {
 
  background-color: #ccffcc;
}
.icon-user-three  {

  background-color: #EEE5AA;
}
.icon-user-four  {

  background-color: #EEE5AA;
}
.icon-user-five  {

  background-color: #EECAB3;
}
.icon-user-six  {

  background-color: #EECAB3;
}
.icon-user-seven  {
  
  background-color: #ffb0bb;
}
.icon-user-eight  {
 
  background-color: #ffb0bb;
}
#loader {
  position: relative;
  top: -4px;
  right: 0px;
  float: left;
  z-index: 100;
  margin: 0px;
  padding: 0px;
}
#hidden-icons {
  display: none;
}
<?php
// include '../../lib/yui/treeview/assets/tree.css';
include '../../lib/yui/container/assets/container.css';
?>

#totalmessage, #count {
  float: left;
  padding-bottom: 2px;
}
#treediv {
  clear: both;
  margin-bottom: 0px;
  padding-bottom: 0px;
}
#mainIcon {
  float: left;
  padding-left: 8px;
  height: 10px;
}
div.aligncenter {
  border: 1px solid #00cccc;
}
div.new {
  width: 480px;
  margin-left: auto;
  margin-right: auto;
  height: 430px;
}
div.right {
  float: right;
  width: 40%;
}
div.left {
  float: left;
  width: auto;
}
div.middle-float {
  width: 100%;
  clear: both;
  text-align: center;
}
div.row {
  height: 30px;
}
div.swatch {
  width: 22px;
  height: 22px;
  float: left;
  margin-right: 3px;
}




/*
Copyright (c) 2007, Yahoo! Inc. All rights reserved.
Code licensed under the BSD License:
http://developer.yahoo.net/yui/license.txt
version: 2.3.0
*/


/* first or middle sibling, no children */
.ygtvtn {
	width:18px; height:22px; 
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -5600px no-repeat; 
}

/* first or middle sibling, collapsable */
.ygtvtm {
	width:18px; height:22px; 
	cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -4000px no-repeat; 
}

/* first or middle sibling, collapsable, hover */
.ygtvtmh {
	width:18px; height:22px; 
	cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -4800px no-repeat; 
}

/* first or middle sibling, expandable */
.ygtvtp {
	width:18px; height:22px; 
	cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -6400px no-repeat; 
}

/* first or middle sibling, expandable, hover */
.ygtvtph {
	width:18px; height:22px; 
	cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -7200px no-repeat; 
}

/* last sibling, no children */
.ygtvln {
	width:18px; height:22px; 
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -1600px no-repeat; 
}

/* Last sibling, collapsable */
.ygtvlm {
	width:18px; height:22px; 
	cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 0px no-repeat; 
}

/* Last sibling, collapsable, hover */
.ygtvlmh {
	width:18px; height:22px; 
	cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -800px no-repeat; 
}

/* Last sibling, expandable */
.ygtvlp { 
	width:18px; height:22px; 
	cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -2400px no-repeat; 
}

/* Last sibling, expandable, hover */
.ygtvlph { 
	width:18px; height:22px; cursor:pointer ;
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -3200px no-repeat; 
}

/* Loading icon */
.ygtvloading { 
	width:18px; height:22px; 
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>treeview-loading.gif) 0 0 no-repeat; 
}

/* the style for the empty cells that are used for rendering the depth 
 * of the node */
.ygtvdepthcell { 
	width:18px; height:22px; 
	background: url(<?php echo $CFG->wwwroot."/lib/yui/treeview/assets/" ?>sprite-orig.gif) 0 -8000px no-repeat; 
}

.ygtvblankdepthcell { width:18px; height:22px; }

/* the style of the div around each node */
.ygtvitem { }  

/* the style of the div around each node's collection of children */
.ygtvchildren {  }  
* html .ygtvchildren { height:2%; }  

/* the style of the text label in ygTextNode */
.ygtvlabel, .ygtvlabel:link, .ygtvlabel:visited, .ygtvlabel:hover { 
	margin-left:2px;
	text-decoration: none;
    background-color: white; /* workaround for IE font smoothing bug */
}

.ygtvspacer { height: 22px; width: 18px; }


.ygtvloading + td, .ygtvlp + td, .ygtvlph + td, .ygtvtp + td, .ygtvtph + td, .ygtvtm + td, .ygtvtmh + td, .ygtvlm + td, .ygtvlmh + td {
  vertical-align:top;
 
}

.ygtvlph , .ygtvtp , .ygtvtm , .ygtvtmh , .ygtvlm , .ygtvlmh  {
  
}
.ygtvspacer {
  height: 22px;
  width: 18px;
}
        
/* Debug styles */

.bd {
  text-align: left;
}

/*
 styles for the config screen pop up
 */

#conf_left {
float:left;
width: 45%;
margin-left: 3px;
} 
#conf_right {
float:right;
width: 45%;
margin-right: 3px;
text-align: right;
} 
#conf-wrapper {
  clear: both;
}
#conf_spacer {
height:1px;
width:1px;
clear: both;
}
#close {
float:right;
}
#confname {
float: left;
font-weight: bold;
}
#dialog {
display:none;
z-index:100;
background:white;
padding:2px;
font:10pt tahoma;
border:1px solid gray;

width:400px;
position:absolute;
overflow-y:scroll;
}
.dialogheader {
width: 100%;
height: 25px;
background-color: #f9eaae;
float: left;
}
#configTree {
  float:left;
  max-width:200px;
}
#configSettings {
  float:right;
  width:150px;
}
#configGroups {
  float:right;
   width:190px;
}