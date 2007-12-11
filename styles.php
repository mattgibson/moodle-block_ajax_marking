.icon-course {
  padding-left: 20px;
  background-image: url(/theme/chameleon/pix/i/course.gif);
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
}
.icon-assign {
  padding-left: 20px;
  background-image: url(/theme/chameleon/pix/mod/assignment/icon.gif);
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
}
.icon-workshop {
  padding-left: 20px;
  background-image: url(/theme/chameleon/pix/mod/workshop/icon.gif);
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
}
.icon-forum {
  padding-left: 20px;
  background-image: url(/theme/chameleon/pix/mod/forum/icon.gif);
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
}
.icon-quiz {
  padding-left: 20px;
  background-image: url(/theme/chameleon/pix/mod/quiz/icon.gif);
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
}
.icon-question {
  padding-left: 20px;
  background-image: url(/theme/chameleon/pix/i/questions.gif);
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
}
.icon-journal {
  padding-left: 20px;
  background-image: url(/theme/chameleon/pix/mod/journal/icon.gif);
  background-repeat: no-repeat;
  background-position: 0 0px;
  background-color: transparent;
}

/* the following 8 styles give different coloured borders to 
   submissions depending on when they were submitted. The 
   colours may not be the best for your theme so change them
   below if needs be. The timings are in javascript.js ata round line
   340. If you have colour blind users, you may need to take contrast into account
   and maybe vary the line style - dotted, dashed, solid.
*/
   
.icon-user-one {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #4ce638;
  border-style: dotted;
  border-width: 2px;
}
.icon-user-two {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #84fa5b;
  border-style: dotted;
  border-width: 2px;
}
.icon-user-three {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #b5fa5b;
  border-style: dotted;
  border-width: 2px;
}
.icon-user-four {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #e3e138;
  border-style: dotted;
  border-width: 2px;
}
.icon-user-five {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #fab95b;
  border-style: dotted;
  border-width: 2px;
}
.icon-user-six {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #fa885b;
  border-style: dotted;
  border-width: 2px;
}
.icon-user-seven {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #fa5b5b;
  border-style: dotted;
  border-width: 2px;
}
.icon-user-eight {
  padding-left: 16px;
  padding-right: 2px;
  background-image: url(/theme/chameleon/pix/t/groupn.gif);
  background-repeat: no-repeat;
  background-position: 2px 2px;
  background-color: transparent; 
  border-color: #ea4040;
  border-style: dotted;
  border-width: 2px;
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
include '../../lib/yui/treeview/assets/tree.css';
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
#icon {
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

/* The following stuff is from /lib/yui/treeview/assest/tree.css but the include does not work due to the icon path not being correct
   from the location this file is run from */
   
/* Copyright (c) 2006 Yahoo! Inc. All rights reserved. */

/* first or middle sibling, no children */
.ygtvtn {
	width:16px; height:22px; 
	background: url(../../lib/yui/treeview/assets/tn.gif) 0 0 no-repeat; 
}

/* first or middle sibling, collapsable */
.ygtvtm {
	width:16px; height:22px; 
	cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/tm.gif) 0 0 no-repeat; 
}

/* first or middle sibling, collapsable, hover */
.ygtvtmh {
	width:16px; height:22px; 
	cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/tmh.gif) 0 0 no-repeat; 
}

/* first or middle sibling, expandable */
.ygtvtp {
	width:16px; height:22px; 
	cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/tp.gif) 0 0 no-repeat; 
}

/* first or middle sibling, expandable, hover */
.ygtvtph {
	width:16px; height:22px; 
	cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/tph.gif) 0 0 no-repeat; 
}

/* last sibling, no children */
.ygtvln {
	width:16px; height:22px; 
	background: url(../../lib/yui/treeview/assets/ln.gif) 0 0 no-repeat; 
}

/* Last sibling, collapsable */
.ygtvlm {
	width:16px; height:22px; 
	cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/lm.gif) 0 0 no-repeat; 
}

/* Last sibling, collapsable, hover */
.ygtvlmh {
	width:16px; height:22px; 
	cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/lmh.gif) 0 0 no-repeat; 
}

/* Last sibling, expandable */
.ygtvlp { 
	width:16px; height:22px; 
	cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/lp.gif) 0 0 no-repeat; 
}

/* Last sibling, expandable, hover */
.ygtvlph { 
	width:16px; height:22px; cursor:pointer ;
	background: url(../../lib/yui/treeview/assets/lph.gif) 0 0 no-repeat; 
}

/* Loading icon */
.ygtvloading { 
	width:16px; height:22px; 
	background: url(../../lib/yui/treeview/assets/loading.gif) 0 0 no-repeat; 
}

/* the style for the empty cells that are used for rendering the depth 
 * of the node */
.ygtvdepthcell { 
	width:16px; height:22px; 
	background: url(../../lib/yui/treeview/assets/vline.gif) 0 0 no-repeat; 
}

.ygtvblankdepthcell { width:16px; height:22px; }

/* the style of the div around each node */
.ygtvitem { }  

/* the style of the div around each node's collection of children */
.ygtvchildren { }  
* html .ygtvchildren { height:2%; }  

/* the style of the text label in ygTextNode */
.ygtvlabel, .ygtvlabel:link, .ygtvlabel:visited, .ygtvlabel:hover { 
	margin-left:2px;
	text-decoration: none;
}

.ygtvspacer { height: 10px; width: 10px; margin: 2px; }

/* Debug styles */
#treediv {
  border-width: 1px;
  border-style: solid;
  border-color: #fff;
}
.bd {
  text-align: left;
}