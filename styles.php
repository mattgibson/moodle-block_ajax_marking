.icon-course, .icon-assign, .icon-workshop, .icon-forum, .icon-quiz, .icon-question, .icon-journal, .icon-group {
  padding-left: 20px;
  padding-bottom: 3px;
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
  white-space: nowrap;
  margin-left: 0px;
  display: block;
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
  top: 3px;
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
  /* clear: both; */
  margin-bottom: 0px;
  padding-bottom: 0px;
  float: left;
  font:10pt tahoma;
}
#mainIcon {
  float: left;
  padding-left: 8px;
  height: 10px;
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
  float: left;
  clear: both;
  background-color: transparent;
}
#close {
  float:right;
  margin: 0px;
  padding: 0px;
}
#confname {
  float: left;
  font-weight: bold;
  width: 50%;
  padding-left: 4px;
  line-height: 15px;
}
#dialog {
  display:none;
  z-index:100;
  background:white;
  padding:0px;
  font:10pt tahoma;
  border:1px solid gray;
  width:400px;
  position:absolute;
  overflow-y:scroll;
}
.dialogheader {
  line-height: 0;
  height: 25px;
  background-color: #f9eaae;
  width: 100%;
  margin: 0px;
}
#configTree {
  float:left;
  max-width:200px;
  padding-top: 4px;
}
#configSettings {
  float:right;
  width:150px;
}
#configGroups {
  float:right;
  width:190px;
 
}
#configIcon {
  float: left;
  position: relative;
  width: 35px;
  line-height: 0;
}
div.block_ajax_marking div.footer {
  border-style: none;
  pading-bottom: 0px;
  height: 30px;
}
