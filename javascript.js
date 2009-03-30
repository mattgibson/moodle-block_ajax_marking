// JavaScript Document for including in the AJAX marking block




AJAXmarking = {


    aidHolder : '',               // this holds the assessment id so it can be accessed by other functions
    sidHolder : '',               // this holds the submission id so it can be accessed by other functions.
                                      // the above 2 variables sometimes hold different things e.g. user id or submission
                                                                      // record id, depending on what sort of node it is
    nodeHolder : '',             // this holds the parent node so it can be referenced by other functions
    compHolder : '',              // this holds the callback function of the parent node so it can be called once all the child nodes have been built
    checkVar : 6,
   
    totalCount : 0,               // all pieces of work to be marked. Updated dynamically by altering this.
    valueDiv : '',             // the div that hold totalCount

    windowobj : '',                // this is the variable used by the openPopup function on the front page. We make it a global
                                       // so we can access the pop up window using DOM later
    // this holds the timer that keeps trying to add the onclick stuff to the pop ups as the pop up loads
    timerVar : '',
    // same but for closing the frames for a workshop
    frameTimerVar : '',
    t : 0,
    main : '',
    config : '',


     /**
      * just moved out from ajaxtree, this needs:
      * @ the tree icon div
      * @ the tree div for construction
      * @ the tree loadcounter (what was this for?)
      * @ the tree ajaxcallback function (same for both?)
      */

     ajaxBuild : function(tree) {
        
                  var sUrl = '';
                  if (tree.loadCounter === 0) {
                          //var img = '<img id="loader" src="'+amVariables.wwwroot+'/lib/yui/treeview/assets/loading.gif" alt=\"loading\" />';
                          
                          tree.icon.setAttribute('class', 'loaderimage');
                          tree.icon.setAttribute('className', 'loaderimage');

                          if (tree.config) { // if this is the config tree, we need to ask for config_courses
                              sUrl = amVariables.wwwroot+'/blocks/ajax_marking/ajax.php?id='+amVariables.userid+'&type=config_main&userid='+amVariables.userid+'';
                          } else {
                              sUrl = amVariables.wwwroot+'/blocks/ajax_marking/ajax.php?id='+amVariables.userid+'&type=main&userid='+amVariables.userid+'';
                          }

                          var request = YAHOO.util.Connect.asyncRequest('GET', sUrl, AMajaxCallback);
                          tree.loadCounter = 1;
                  }
          },



  /**
   * ajax success function which checks the data coming in and calls the right function.
   * @
   */



  AJAXsuccess : function (o) {
              // alert('ajax success');
                  var type = null;
                  var responseArray = null;
                  var label = '';
                 

                  // uncomment for debugging output at top of block
                  // if (userid == 2) {

                  // }
                  //alert(o.responseText);
                  //var tempVar = o.responseText;
                  //if (AJAXmarking.count < 2) {
                  //    AJAXmarking.count++;
                  //    var checkDiv = document.getElementById("conf_left");
                  //    checkDiv.innerHTML = o.responseText;
                // }

                  responseArray = YAHOO.lang.JSON.parse(o.responseText);
                  //responseArray = eval(o.responseText);

                  type = responseArray[0].type; // fist object holds data about what kind of nodes we have so we can fire the right function.
                  responseArray.shift(); // remove the data object, leaving just the node objects


                

                  switch (type) {

                  case 'main':
                         // alert('main courses');
                          AJAXmarking.makeCourseNodes(responseArray, AJAXmarking.main);
                          //ie_width();
                          break;

                  case 'course':
                         
                          AJAXmarking.makeAssessmentNodes(responseArray, AJAXmarking.main);
                          AJAXmarking.ie_width();
                          break;

                  case 'quiz_question':
                        AJAXmarking.makeAssessmentNodes(responseArray, AJAXmarking.main);
                       // ie_width();
                        break;

                  case 'groups':

                          AJAXmarking.makeGroupNodes(responseArray, AJAXmarking.main);
                         // ie_width();
                          break;

                  case 'submissions':

                          AJAXmarking.makeSubmissionNodes(responseArray, AJAXmarking.main);
                         // ie_width();
                          break;

                  case 'config_main':
                          //alert('config main');
                          AJAXmarking.makeCourseNodes(responseArray, AJAXmarking.config);
                          break;

                  case 'config_course':

                          AJAXmarking.makeAssessmentNodes(responseArray, AJAXmarking.config);
                          break;

                  case 'config_groups':

                          AJAXmarking.makeGroupsList(responseArray, AJAXmarking.config);
                          break;

                  case 'config_set': //just need to un-disable the radio button

                          if (responseArray[0].value === false) {
                              label = document.createTextNode(amVariables.configNothingString);
                              AJAXmarking.removeNodes(AJAXmarking.config.status);
                              AJAXmarking.config.status.appendChild(label);
                                  //AJAXmarking.config.status.innerHTML = 'AJAX connectionerror';
                          } else {
                                  AJAXmarking.enableRadio();
                          }
                          break;

                  case 'config_check':

                          var checkId = 'config'+responseArray[0].value; // make the id of the radio button div
                          //alert(responseArray[0].value);
                          document.getElementById(checkId).checked = true; // make the radio button on screen match the value in the database that was just returned.
                          //if its the groups one, make the groups bit
                          if (responseArray[0].value == 2) {
                              responseArray.shift(); // remove the config bit leaving just the groups.
                              AJAXmarking.makeGroupsList(responseArray); //make the groups bit
                          }
                          AJAXmarking.enableRadio(); //allow the radio buttons to be clicked again
                          break;

                  case 'config_group_save':

                          if (responseArray[0].value === false) {
                              AJAXmarking.config.status.innerHTML = 'AJAX error';
                          } else {
                              AJAXmarking.enableRadio();
                          }

                          break;

                  }// end switch
          },

          AJAXfailure : function (o)
          {
                  if (o.tId == -1) {
                          div.innerHTML =  amVariables.collapseString;
                  }
                  if (o.tId === 0) {
                          div.innerHTML = amVariables.connectFail;
                  }
          },

    /**
     * Creates the initial nodes for both the main block tree and configuration tree.
     */

     makeCourseNodes : function(nodesArray, AJAXtree) {
                   
                  var label = '';
                
                  // make the array of nodes
                  var nodesLeng = nodesArray.length;

                  if (nodesLeng === 0) { // the array is empty, so say there is nothing to mark
                        if (AJAXtree.treeDiv === 'treediv') {
                            label = document.createTextNode(amVariables.configNothingString);
                        } else {
                            label = document.createTextNode(amVariables.nothingString);
                        }
                        AJAXtree.div.appendChild(label);
                        AJAXtree.icon.removeAttribute('class', 'loaderimage');
                        AJAXtree.icon.removeAttribute('className', 'loaderimage');
                  } else { // there is a tree to be drawn

                      // cycle through the array and make the nodes
                    
                      for (n=0;n<nodesLeng;n++) {
                          if (AJAXtree.treeDiv === 'treediv') { //only show the marking totals if its not a config tree
                                  label = nodesArray[n].name+' ('+nodesArray[n].count+')';
                          } else {
                                  label = nodesArray[n].name;
                          }
                     
                          var tmpNode1 = new YAHOO.widget.TextNode(nodesArray[n], AJAXtree.root, false);

                          // save reference in the map for the context menu
                          AJAXtree.textNodeMap[tmpNode1.labelElId] = tmpNode1;

                          tmpNode1.labelStyle = 'icon-course';
                          tmpNode1.setDynamicLoad(AJAXmarking.loadNodeData);
                       }

                         // now make the tree, add the total at the top and remove the loading icon
                         AJAXtree.tree.render();

                         // get rid of the loading icon - IE6 is rubbish so use 2 methods
                         AJAXtree.icon.removeAttribute('class', 'loaderimage');
                         AJAXtree.icon.removeAttribute('className', 'loaderimage');
                         
                         // add onclick events
                          if (!AJAXtree.config) {

                              // Alter total count above tree
                              label = document.createTextNode(amVariables.totalMessage);
                              var total = document.getElementById('totalmessage');
                              AJAXmarking.removeNodes(total);
                              total.appendChild(label);
                              AJAXmarking.updateTotal();

                              AJAXtree.tree.subscribe("clickEvent", function(oArgs) {
                        
                                // ref saves space
                                var nd = oArgs.node;

                                // putting window.open into the switch statement causes it to fail in IE6. No idea why.
                                var popUpAddress = amVariables.wwwroot;
                                var popUpArgs = 'menubar=0,location=0,scrollbars,resizable,width=780,height=500';
                                var timerFunction = '';


                                switch (nd.data.type) {

                                    case 'quiz_answer':
                                          //alert(nd.data.type);
                                          popUpAddress += '/mod/quiz/report.php?mode=grading&action=grade&q='+nd.parent.parent.data.id+'&questionid='+nd.data.aid+'&userid='+nd.data.sid+'';
                                          timerFunction = 'AJAXmarking.quizOnLoad(\''+nd.data.id+'\')';
                                         
                                          break;

                                    case 'assignment_answer':
                                       // alert('timerFunction');
                                        popUpAddress += '/mod/assignment/submissions.php?id='+nd.data.aid+'&userid='+nd.data.sid+'&mode=single&offset=0';
                                        timerFunction = 'AJAXmarking.assignmentOnLoad(\''+nd.data.id+'\', \''+nd.data.sid+'\')';
                                      break;

                                   case 'workshop_answer':

                                          popUpAddress += '/mod/workshop/assess.php?id='+nd.data.aid+'&sid='+nd.data.sid+'&redirect='+amVariables.wwwroot+'';
                                          timerFunction = 'AJAXmarking.workshopOnLoad(\''+nd.data.id+'\')';
                                      break;

                                   case 'discussion':

                                          popUpAddress += '/mod/forum/discuss.php?d='+nd.data.aid+'#p'+nd.data.sid+'';
                                          timerFunction = 'AJAXmarking.forumOnLoad(\''+nd.data.id+'\')';
                                      break;

                                   case 'journal':

                                         popUpAddress += '/mod/journal/report.php?id='+nd.data.aid+'';
                                         timerFunction = 'AJAXmarking.journalOnLoad(\''+nd.data.id+'\')';
                                      break;
                                   } //end switch

                                   if (timerFunction !== '') {

                                       AJAXmarking.windowobj = window.open(popUpAddress, '_blank', popUpArgs);
                                       AJAXmarking.timerVar =  window.setInterval(timerFunction, 500);
                                       //AJAXmarking.quizOnLoad(nd.data.id);
                                       AJAXmarking.windowobj.focus();

                                       return false;
                                   }
                                   return true;

                      }); // end subscribe
                  } else {

                      // procedure for config tree nodes:
                      AJAXtree.tree.subscribe('clickEvent', function(oArgs) {

                          var title = document.getElementById('configInstructions');
                          var check = document.getElementById('configshowform');
                          //var groups = document.getElementById('configGroups');
                          AJAXmarking.clearGroupConfig();

                          if (oArgs.node.data.type == 'config_course') {

                              AJAXmarking.removeNodes(title);
                          } else {

                              label = document.createTextNode(oArgs.node.data.name);
                              title.appendChild(label);
                          }

                          if (oArgs.node.data.type !== 'config_course') {
                              check.style.color = '#AAA';

                              var hidden1 = document.createElement('input');
                              hidden1.type  = 'hidden';
                              hidden1.name  = 'course';
                              hidden1.value = oArgs.node.parent.data.id;
                              check.appendChild(hidden1);

                              var hidden2 = document.createElement('input');
                              hidden2.setAttribute('type', 'hidden');
                              hidden2.setAttribute('name', 'assessment');
                              hidden2.setAttribute('value', oArgs.node.data.id);
                              check.appendChild(hidden2);

                              var hidden3 = document.createElement('input');
                              hidden3.type  = 'hidden';
                              hidden3.name  = 'assessmenttype';
                              hidden3.value = oArgs.node.data.type;
                              check.appendChild(hidden3);

                              // fixes nasty IE6 bug: http://cf-bill.blogspot.com/2006/03/another-ie-gotcha-dynamiclly-created.html
                              try{
                                  box1 = document.createElement('<input type="radio" name="showhide" />');
                              }catch(err){
                                  box1 = document.createElement('input');
                              }
                              box1.setAttribute('type','radio');
                              box1.setAttribute('name','showhide');
                              box1.value = 'show';
                              box1.id    = 'config1';
                              box1.onclick = function() {AJAXmarking.showHideChanges(this);};
                              check.appendChild(box1);

                              var box1text = document.createTextNode('Show');
                              check.appendChild(box1text);
                              var breaker = document.createElement('br');
                              check.appendChild(breaker);

                              try{
                                  box2 = document.createElement('<input type="radio" name="showhide" />');
                              }catch(error){
                                  box2 = document.createElement('input');
                              }
                              box2.setAttribute('type','radio');
                              box2.setAttribute('name','showhide');
                              box2.value = 'groups';
                              box2.id    = 'config2';
                              box2.disabled = true;
                              box2.onclick = function() {AJAXmarking.showHideChanges(this);};
                              check.appendChild(box2);

                              var box2text = document.createTextNode('Show by group');
                              check.appendChild(box2text);
                              var breaker2 = document.createElement('br');
                              check.appendChild(breaker2);


                              try{
                                  box3 = document.createElement('<input type="radio" name="showhide" />');
                              }catch(errors){
                                  box3 = document.createElement('input');
                              }
                              box3.setAttribute('type','radio');
                              box3.setAttribute('name','showhide');
                              box3.value = 'hide';
                              box3.id    = 'config3';
                              box3.disabled = true;
                              box3.onclick = function() {AJAXmarking.showHideChanges(this);};
                              check.appendChild(box3);

                              var box3text = document.createTextNode('Hide');
                              check.appendChild(box3text);
                          }

                          // now, we need to find out what the current group mode is and display that box as checked.
                          var checkUrl = amVariables.wwwroot+'/blocks/ajax_marking/ajax.php?id='
                                         +oArgs.node.parent.data.id+'&assessmenttype='+oArgs.node.data.type
                                         +'&assessmentid='+oArgs.node.data.id+'&userid='+amVariables.userid
                                         +'&type=config_check';
                          var request = YAHOO.util.Connect.asyncRequest('GET', checkUrl, AMajaxCallback);

                          return false;
                      }
                  ); //end tree.subscribe
              }// end else
            // this.tooltips();
          } //end else
      }, //end function

      /**
       * Obsolete function to make tooltips for the whole tree using YUI container widget. Overkill, so disabled
       */
      tooltips : function(tree) {
         
                  var name = navigator.appName;
                  if (name.search('iPhone') == -1) {
                      // this is disabled for IE because, although useful, in IE6 (assuming others too) the tooltips seem to sometimes remain as an invisible div on top
                      // of the tree structure once nodes has expanded, so that some of the child nodes are unclickable. Firefox is ok with it. This is a pain
                      // because a person may not remember the full details of the assignment that was set and a tooltip is better than leaving the front page.
                      // I will re-enable it once I find a fix

                      var i = 0;
                      var j = 0;
                      var k = 0;
                      var m = 0;
                      var n = 0;
                      var control = '';
                      if (tree.div != 'treeDiv') {
                          return false;
                      }
                      // 1. all courses loop
                      var numberOfCourses = tree.root.children.length;
                      for (i=0;i<numberOfCourses;i++) {
                          node = tree.root.children[i];
                          AJAXmarking.make_tooltip(node);
                          var numberOfAssessments = tree.root.children[i].children.length;
                          for (j=0;j<numberOfAssessments;j++) {
                              // assessment level
                              node = tree.root.children[i].children[j];
                              AJAXmarking.make_tooltip(node);
                              var numberOfThirdLevelNodes = tree.root.children[i].children[j].children.length;
                              for (k=0;k<numberOfThirdLevelNodes;k++) {
                                  // users level (or groups)
                                  node = tree.root.children[i].children[j].children[k];
                                  check = node.data.time;
                                  if (typeof(check) !== null) {
                                      AJAXmarking.make_tooltip(node);
                                  }
                                  var numberOfFourthLevelNodes = node.children.length;
                                  for (m=0;m<numberOfFourthLevelNodes;m++) {
                                      node = tree.root.children[i].children[j].children[k].children[m];
                                      AJAXmarking.make_tooltip(node);
                                      var numberOfFifthLevelNodes = node.children.length;
                                      for (n=0;n<numberOfFifthLevelNodes;n++) {
                                         node = tree.root.children[i].children[j].children[k].children[m].children[n];
                                         AJAXmarking.make_tooltip(node);
                                     }
                                  }
                              }
                          }
                      }
                      return true;
                  }
                  return false;
          },


      /**
       * This function enables the config popup radio buttons again after the AJAX request has
       * returned a success code.
       *
       */

      enableRadio : function() {
                  var h ='';
                  var radio = document.getElementById('configshowform');
                  radio.style.color = '#000';

                  for (h = 0; h < radio.childNodes.length; h++) {
                      if (radio.childNodes[h].name == 'showhide') {
                          radio.childNodes[h].setAttribute ('disabled', false);
                          radio.childNodes[h].disabled = false;
                      }
                  }
                  var groupDiv = document.getElementById('configGroups');
                  groupDiv.style.color = '#000';

                  for (h = 0; h < groupDiv.childNodes.length; h++) {
                      if (groupDiv.childNodes[h].type == 'checkbox') {
                          groupDiv.childNodes[h].setAttribute ('disabled', false);
                          groupDiv.childNodes[h].disabled = false;
                      }
                  }

          },

          /**
           * This function disables the radio buttons when AJAX request is sent
           */

          disableRadio : function() {
                  var h ='';
                  var radio = document.getElementById('configshowform');
                  radio.style.color = '#AAA';

                  for (h = 0; h < radio.childNodes.length; h++) {
                      if (radio.childNodes[h].type == 'radio') {
                          radio.childNodes[h].setAttribute ('disabled',  true);

                      }
                  }
                  var groupDiv = document.getElementById('configGroups');
                  groupDiv.style.color = '#AAA';

                  for (h = 0; h < groupDiv.childNodes.length; h++) {
                      if (groupDiv.childNodes[h].type == 'checkbox') {
                          groupDiv.childNodes[h].setAttribute ('disabled', true);
                      }
                  }
          },


          /**
           * this function is called when a node is clicked (expanded) and makes the ajax request
           */

          loadNodeData : function(node, onCompleteCallback) {
            
            /// store details of the node that has been clicked in globals for reference by later callback function
            AJAXmarking.nodeHolder = node;
            AJAXmarking.compHolder = onCompleteCallback;

            /// request data using AJAX
            var sUrl = amVariables.wwwroot+'/blocks/ajax_marking/ajax.php?id='+node.data.id+'&type='+node.data.type+'&userid='+amVariables.userid+'';

            if (typeof node.data.group  != 'undefined') { sUrl += '&group='+node.data.group; } //add group id if its there
            if (node.data.type == 'quiz_question') { sUrl += '&quizid='+node.parent.data.id; } //add quiz id if this is a question node
           
            var request = YAHOO.util.Connect.asyncRequest('GET', sUrl, AMajaxCallback);
          },



          /**
           * function to update the parent assessment node when it is refreshed dynamically so that
           * if more work has been found, or a piece has now been marked, the count for that label will be accurate
           */

          parentUpdate : function(AJAXtree, node) {

                  var counter = node.children.length;
                  //alert('length: '+counter);
                  //alert('type: '+node.data.type);
                  
                  //alert('assessmentid: '+node.data.assessmentid);
                  if (counter === 0) {
                          AJAXtree.tree.removeNode(node, true);
                  } else {

                          if (node.data.type == 'course' || node.children[0].data.gid != 'undefined' || node.data.type == 'forum' || node.data.type == 'quiz') { // we need to sum child counts
                                  //alert('in loop');
                                  //alert('parent index: '+node.parent.index);
                                  var tempCount = 0;
                                  var tempStr = '';
                                  for (i=0;i<counter;i++) {

                                          tempStr = node.children[i].data.count;
                                          //alert('node count: '+tempStr);
                                          tempCount += parseInt(tempStr, 10);
                                  }
                                  
                                  //alert('total: '+tempCount);
                                  AJAXmarking.countAlter(node, tempCount);
                          } else { // its an assessment node, so we count the children
                                  AJAXmarking.countAlter(node, counter);
                          }
                          //alert('parent index: '+node.parent.index);
                          // TODO - does this still work?
                          AJAXtree.tree.root.refresh();

                  }
          },


         /**
          * function to create tooltips. When root.refresh() is called it somehow wipes
          * out all the tooltips, so it is necessary to rebuild them
          * each time part of the tree is collapsed or expanded
          * tooltips for the courses are a bit pointless, so its just the assignments and submissions
          *
          *
          * n.b. the width of the tooltips is fixed because not specifying it makes them go narrow in IE6. making them 100% works fine in IE6 but makes FF
          * stretch them across the whole page. 200px is a guess as to a good width for a 1024x768 screen based on the width of the block. Change it in both places below
          * if you don't like it
          *
          * IE problem - the tooltips appear to interfere with the submission nodes using ie, so that they are not always clickable, but only when the user
          * clicks the node text rather than the expand (+) icon. Its not related to the timings as using setTimeout to delay the generation of the tooltips
          * makes no difference
          */


          make_tooltip : function(node) {

              tempLabelEl = node.getLabelEl();
              tempText = node.data.summary;
              tempTooltip = new YAHOO.widget.Tooltip('tempTooltip', { context:tempLabelEl, text:tempText, showdelay:0, hidedelay:0, width:150, iframe:false, zIndex:1110} );

          },

         /**
          * function to build the assessment nodes once the AJAX request has returned a data object
          */

          makeAssessmentNodes : function(nodesArray, AJAXtree) {
              // uncomment for verbatim on screen output of the AJAX response for assessment and submission nodes
              // this.div.innerHTML += o.responseText;
              // alternatively, use the firebug extension for mozilla firefox - less messy.

              var tmpNode2 = '';
         
              // First the courses array
              var  nodesLeng = nodesArray.length;

              // cycle through the array and make the nodes
              for (m=0;m<nodesLeng;m++) {

                  // set the correct language strings for the tooltip summaries
                  // TODO - set the amVariables to have an array and the use amVarables[type]

                  //Set the summary so that it makes sense.
                  switch (nodesArray[m].type) {

                  case 'assignment':
                          nodesArray[m].summary = amVariables.assignmentString+' '+nodesArray[m].summary+'';
                          break;
                  case 'workshop':
                          nodesArray[m].summary = amVariables.workshopString+' '+nodesArray[m].summary+'';
                          break;
                  case 'forum':
                          nodesArray[m].summary = amVariables.forumString+' '+nodesArray[m].summary+'';
                          break;
                  case 'quiz':
                          nodesArray[m].summary = amVariables.quizString+' '+nodesArray[m].summary+'';
                          break;
                  case 'journal':
                          nodesArray[m].summary = amVariables.journalString+' '+nodesArray[m].summary+'';
                          break;
                  case 'journal_submissions':
                          nodesArray[m].summary = amVariables.journalString+' '+nodesArray[m].summary+'';
                          break;
                  }

                  // use the object to create a new node
                  tmpNode2 = new YAHOO.widget.TextNode(nodesArray[m], AJAXmarking.nodeHolder , false);

                  AJAXtree.textNodeMap[tmpNode2.labelElId] = tmpNode2;

                  // style the node acording to its type
                  switch (nodesArray[m].type) {

                  case 'assignment':
                          tmpNode2.labelStyle = 'icon-assignment';
                          break;
                  case 'workshop':
                          tmpNode2.labelStyle = 'icon-workshop';
                          break;
                  case 'forum':
                          tmpNode2.labelStyle = 'icon-forum';
                          break;
                  case 'quiz_question':
                          tmpNode2.labelStyle = 'icon-quiz_question';
                          break;
                  case 'quiz':
                          tmpNode2.labelStyle = 'icon-quiz';
                          break;
                  case 'journal':
                          tmpNode2.labelStyle = 'icon-journal';
                          break;
                  }

                  // set the node to load data dynamically, unless it is marked as not dynamic e.g. journal
                  if ((!AJAXtree.config) && (nodesArray[m].dynamic == 'true')) {
                     tmpNode2.setDynamicLoad(AJAXmarking.loadNodeData);
                  }

              } //end of per-node loop

              //don't do the totals if its a config tree
              if (!AJAXtree.config) {
                  AJAXmarking.parentUpdate(AJAXtree, AJAXmarking.nodeHolder );
              }
             
              // finally, run the function that updates the original node and adds the children
              AJAXmarking.compHolder();

              if (!AJAXtree.config) {
                  AJAXmarking.updateTotal();
              }
              AJAXtree.root.refresh();

              // then add tooltips.
              //this.tooltips();

          },

    /**
     * makes the submission nodes for each student with unmarked work. Takes ajax data object as input
     */
    makeSubmissionNodes : function(nodesArray, AJAXtree) {

            //var myobj = '';
            var tmpNode3 = '';

            for (var k=0;k<nodesArray.length;k++) {

                // set up a unique id so the node can be removed when needed
                uniqueId = nodesArray[k].type + nodesArray[k].aid + 'sid' + nodesArray[k].sid + '';

                // set up time-submitted thing for tooltip. This is set to make the time match the browser's local timezone,
                // but I can't find a way to use the user's specified timezone from \$USER. Not sure if this really matters.

                var secs = parseInt(nodesArray[k].seconds, 10);
                // javascript likes to work in miliseconds, whereas moodle uses unix format (whole seconds)
                var time = parseInt(nodesArray[k].time, 10)*1000; 
                // make a new data object
                var d = new Date(); 
                // set it to the time we just got above
                d.setTime(time);  

                // altered - does this work?
                tmpNode3 = new YAHOO.widget.TextNode(nodesArray[k], AJAXmarking.nodeHolder , false);

                AJAXtree.textNodeMap[tmpNode3.labelElId] = tmpNode3;

                // apply a style according to how long since it was submitted

                if (secs < 21600) {
                    // less than 6 hours
                    tmpNode3.labelStyle = 'icon-user-one';
                } else if (secs < 43200) {
                    // less than 12 hours
                    tmpNode3.labelStyle = 'icon-user-two';
                } else if (secs < 86400) {
                    // less than 24 hours
                    tmpNode3.labelStyle = 'icon-user-three';
                } else if (secs < 172800) {
                    // less than 48 hours
                    tmpNode3.labelStyle = 'icon-user-four';
                } else if (secs < 432000) {
                    // less than 5 days
                    tmpNode3.labelStyle = 'icon-user-five';
                } else if (secs < 864000) {
                    // less than 10 days
                    tmpNode3.labelStyle = 'icon-user-six';
                } else if (secs < 1209600) {
                    // less than 2 weeks
                    tmpNode3.labelStyle = 'icon-user-seven';
                } else {
                    // more than 2 weeks
                    tmpNode3.labelStyle = 'icon-user-eight';
                }

            } 

            // update all the counts on the various nodes
            AJAXmarking.parentUpdate(AJAXtree, AJAXmarking.nodeHolder);
            //might be a course, might be a group if its a quiz by groups
            AJAXmarking.parentUpdate(AJAXtree, AJAXmarking.nodeHolder.parent);
            if (!AJAXmarking.nodeHolder.parent.parent.isRoot()) {
                this.parentUpdate(AJAXtree, AJAXmarking.nodeHolder.parent.parent);
                if (!AJAXmarking.nodeHolder.parent.parent.parent.isRoot()) {
                    AJAXmarking.parentUpdate(AJAXtree, AJAXmarking.nodeHolder.parent.parent.parent);
                }
            }

            // finally, run the function that updates the original node and adds the children
            AJAXmarking.compHolder();
            AJAXmarking.updateTotal();

            // then add tooltips.
            //this.tooltips();
    
      },


      makeGroupNodes : function(responseArray, AJAXtree) {
          // need to turn the groups for this course into an array and attach it to the course node. Then make the groups bit on screen
          // for the config screen??
        
          var arrayLength = responseArray.length;
          var tmpNode4 = '';
          
          for (var n =0; n<arrayLength; n++) {

              tmpNode4 = new YAHOO.widget.TextNode(responseArray[n], AJAXmarking.nodeHolder, false);

              AJAXtree.textNodeMap[tmpNode4.labelElId] = tmpNode4;

              tmpNode4.labelStyle = 'icon-group';

              // if the groups are for journals, it is impossible to display individuals, so we make the
              // node clickable so that the pop up will have the group screen.
              // TODO make this into a dynamic thing based on another attribute of the data object
              if (responseArray[n].type !== 'journal') {
                  tmpNode4.setDynamicLoad(AJAXmarking.loadNodeData);
              }
          }

          AJAXmarking.parentUpdate(AJAXtree, AJAXmarking.nodeHolder);
          AJAXmarking.parentUpdate(AJAXtree, AJAXmarking.nodeHolder.parent);
          AJAXmarking.compHolder();
          AJAXmarking.updateTotal();

      },


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// funtion to refresh all the nodes once the update operations have all been carried out by saveChangesAJAX()
   ///////////////////////////////////////////////////
    refreshRoot : function(tree) {
            tree.root.refresh();
            if (tree.root.children.length === 0) {
                //var total = document.getElementById("totalmessage");
                //var count = document.getElementById("count");
                AJAXmarking.removeNodes(document.getElementById("totalmessage"));
                AJAXmarking.removeNodes(document.getElementById("count"));
                tree.div.appendChild(document.createTextNode(amVariables.nothingString));
            }
    },


    AJAXtree : function(treeDiv, icon, statusDiv, config) {

            this.loadCounter = 0;
           
            // YAHOO.widget.TreeView.preload();
            this.tree    = new YAHOO.widget.TreeView(treeDiv);
            
            // holds a map of textnodes for the context menu 
            // http://developer.yahoo.com/yui/examples/menu/treeviewcontextmenu.html
            this.textNodeMap = {};

            this.treeDiv = treeDiv;
            this.icon    = document.getElementById(icon);
            this.div     = document.getElementById(statusDiv);
            this.config  = config;
            
            /// set the removal of all child nodes each time a node is collapsed (forces refresh)
            // not needed for config tree
            if (!config) { // the this keyword gets confused and can't be used for this
              this.tree.subscribe('collapseComplete', function(node) {
                 // TODO - make this not use a hardcoded reference
                 AJAXmarking.main.tree.removeChildren(node);
              });
            } 

            this.root = this.tree.getRoot();


            this.contextMenu = new YAHOO.widget.ContextMenu("maincontextmenu", {
                                    trigger: treeDiv,
                                    lazyload: true,  
                                    itemdata: [
                                       // { text: this.currentTextNode.label  },
                                        { text: "Current group mode:", onclick: { } }
                                       
                                    ] });

            this.contextMenu.subscribe("triggerContextMenu",  function (p_oEvent) {
	 
                    var oTarget = this.contextEventTarget,
                        Dom = YAHOO.util.Dom;
                        //alert(count(this.textNodeMap));

                    /*
                         Get the TextNode instance that that triggered the
                         display of the ContextMenu instance.
                    */

                    //var oTextNode = Dom.hasClass(oTarget, "ygtvlabel") ?
                    //    oTarget : Dom.getAncestorByClassName(oTarget, "ygtvlabel");

                       // alert(oTarget.id);

                    if (oTarget) {

                        this.currentTextNode = this.textNodeMap[oTarget.id];

                    } else {
                        alert('no');
                        // Cancel the display of the ContextMenu instance.

                        this.cancel();
                    }

                }
            );

          //alert('end of ajaxtree');
       // this.tree = AJAXmarking.make_tree();
  }, // end AJAXtree build function



 ////////////////////////////////////////////////////////////////////////////////////////////////////////////
          /// funtion to refresh all the nodes once the operations have all been carried out - workshop frames version
          ////////////////////////////////////////////////////////////////////////////////////////////////////////////

          refreshRootFrames : function(tree) {
                   tree.root.refresh();
                   if (tree.root.children.length === 0) {
                           //document.getElementById("totalmessage").innerHTML = '';
                           AJAXmarking.removeNodes(document.getElementById("totalmessage"));
                           //document.getElementById("count").innerHTML = '';
                           AJAXmarking.removeNodes(document.getElementById("count"));
                           //tree.div.innerHTML += amVariables.nothingString;
                           AJAXmarking.removeNodes(tree.div);
                           tree.div.appendChild(document.createTextNode(amVariables.nothingString));
                  }
          },



          /**
           * function to update the total marking count by a specified number and display it
           */
          

          updateTotal : function() {
                  
                  var count = 0;
                  var countTemp = 0;
                  //alert('updatetotal root');
                  var children = AJAXmarking.main.root.children;
//alert(childrenLength);
                  for (i=0;i<children.length;i++) {
                          countTemp = children[i].data.count;
                          count = count + parseInt(countTemp, 10);

                  }
                  //alert(count);
                  if (count > 0) {
                      var countDiv = document.getElementById('count');
                      AJAXmarking.removeNodes(countDiv);
                      countDiv.appendChild(document.createTextNode(count));
                  }
          },


          /////////////////////////////////////////////////////////////////////
          /// These functions are called from the marking pop-ups.
          //////////////////////////////////////////////////////////////////////



          /*
          this.saveChangesAJAXjournal = function(thisNodeId, parentNodeId) {

          /// remove the node that was just marked
                   var checkNode = "";
                   checkNode = tree.getNodeByProperty("assessmentid", thisNodeId);
                   this.tree.removeNode(checkNode, true);

          /// get the parent node and alter its label count to have one less in the total count. Remove the node if the count is 0
                   var parentNode = "";
                   parentNode = this.tree.getNodeByProperty("cid", parentNodeId);
                   parentUpdate(parentNode);


          /// refresh the tree to redraw the nodes with the new labels
                   this.refreshRoot();
                   updateTotal();
                   tooltips();
          };
          */

    /**
    * this function updates the tree to remove the node of the pop up that has just been marked, then it updates the parent nodes and refreshes the tree
    *
    */

    saveChangesAJAX : function(loc, AJAXtree, thisNodeId, frames) {
        //alert('savechangesajax');
        var checkNode = "";
        var parentNode = "";
        var marker = 0;
        // var tree = AJAXmarking.main;

        /// remove the node that was just marked

        checkNode = AJAXtree.tree.getNodeByProperty("id", thisNodeId);

        parentNode  = AJAXtree.tree.getNodeByIndex(checkNode.parent.index);
        parentNode1 = AJAXtree.tree.getNodeByIndex(parentNode.parent.index);
        // this will be the course node if there is a quiz or groups
        if (!parentNode1.parent.isRoot()) { // the node above is not root so we need it
            parentNode2 = AJAXtree.tree.getNodeByIndex(parentNode1.parent.index);
        }

        if ((typeof parentNode2 != 'undefined') && (!parentNode2.parent.isRoot())) {
        // this will be the course node if there is both a quiz and groups
            parentNode3 = AJAXtree.tree.getNodeByIndex(parentNode2.parent.index);
        }

        AJAXtree.tree.removeNode(checkNode, true);

        AJAXmarking.parentUpdate(AJAXtree, parentNode);
        AJAXmarking.parentUpdate(AJAXtree, parentNode1);
        if (typeof parentNode2 != 'undefined') {
            AJAXmarking.parentUpdate(AJAXtree, parentNode2);
        }
        if (typeof parentNode3 != 'undefined') {
            AJAXmarking.parentUpdate(AJAXtree, parentNode3);
        }


        /// refresh the tree to redraw the nodes with the new labels
        if (typeof(frames) != 'undefined') {
            AJAXmarking.refreshRootFrames(AJAXtree);
        } else {
            AJAXmarking.refreshRoot(AJAXtree);
        }

        AJAXmarking.updateTotal();
        AJAXmarking.tooltips(AJAXtree);

        if (loc != -1) { // no need if its an assignment as the pop up is self closing
            windowLoc = 'AJAXmarking.afterLoad(\''+loc+'\')';
            setTimeout(windowLoc, 500);
        }
    },

          /// this function updates the tree to remove the node of the pop up that has just been marked, then it updates the parent nodes and refreshes the tree
          // deprecated?
          /*
          this.saveChangesAJAXquiz = function(thisNodeId, parentNodeId, quizNodeId, courseNodeId) {

          //alert ('save ajax quiz fired');
          /// remove the node that was just marked
                   var checkNode = "";
                   checkNode = this.tree.getNodeByProperty("id", thisNodeId);
                   this.tree.removeNode(checkNode, true);

          /// get the parent node and alter its label count to have one less in the total count. Remove the node if the count is 0
                   var parentNode = "";
                   parentNode = this.tree.getNodeByProperty("assessmentid", parentNodeId);
                   this.parentUpdate(parentNode);

          /// get the parent node and alter its label count to have one less in the total count. Remove the node if the count is 0
                   var quizNode = "";
                   parentNode = this.tree.getNodeByProperty("assessmentid", quizNodeId);
                   this.parentUpdate(parentNode);

          /// now do the same for the course node
             //  alert('id= '+courseNodeId);
                   var courseNode = "";
                   courseNode = this.tree.getNodeByProperty("cid", courseNodeId);
                   this.parentUpdate(courseNode);

          /// refresh the tree to redraw the nodes with the new labels
                   this.refreshRoot();
                   this.updateTotal();
                   this.tooltips();
          };
          */
          ////////////////////////////////////////////////////////////////////////////////////////
          // same as the above function, but adjusted to work when called from the workshop frames
          ////////////////////////////////////////////////////////////////////////////////////////
          /*
          this.saveChangesAJAXFrames = function(thisNodeId, parentNodeId, courseNodeId) {

          /// remove the node that was just marked
                   var checkNode = "";
                   checkNode = this.tree.getNodeByProperty("id", thisNodeId);
                   this.tree.removeNode(checkNode, true);

          /// get the parent node and alter its label count to have one less in the total count. Remove the node if the count is 0
                   var parentNode = "";
                   parentNode = this.tree.getNodeByProperty("assessmentid", parentNodeId);
                   this.parentUpdate(parentNode);

          /// now do the same for the course node

                   var courseNode = "";
                   courseNode = this.tree.getNodeByProperty("cid", courseNodeId);
                   this.parentUpdate(courseNode);


          /// refresh the tree to redraw the nodes with the new labels
                   this.refreshRootFrames();
                   this.updateTotal();
                   this.tooltips();
           };


          /// this function holds the original javascript from the save changes onclick for the Assignment pop up
          this.saveChangesAssignment = function() {
             document.getElementById('submitform').menuindex.value = document.getElementById('submitform').grade.selectedIndex;
             this.saveChangesAJAX();
          };
                  */
          /////////////////////////////////////////////////////////////////
          // Refresh tree function - for Collapse &amp; refresh link
          /////////////////////////////////////////////////////////////////

          refreshTree : function(treeObj) {
             // main.div.innerHTML = '';
                  treeObj.loadCounter = 0;
                  //var treeDiv = treeObj.treeDiv;
                 // var icon = treeObj.icon;
                  //var status = treeObj.statusDiv;
                  //delete treeObj.tree;
                  if (treeObj.root.children.length >0) {
                      treeObj.tree.removeChildren(treeObj.root);
                      treeObj.root.refresh();
                  }
                  // this.tree = '';
                  // tree.tree = new AJAXmarking.AJAXtree(treeDiv, icon, status);
                  // treeObj.tree = new YAHOO.widget.TreeView(treeDiv);
                  //treeObj.tree.subscribe('collapseComplete', function(node) {
                  //    this.tree.removeChildren(node);
                  //});
                  // tree.root = '';
                  // alert(tree.div);
                  AJAXmarking.removeNodes(treeObj.div);
                  //AJAXmarking.removeNodes(treeObj.treeDiv);
                  //treeObj.div.innerHTML = '';
                  //treeObj.treeDiv.innerHTML = '';
                  AJAXmarking.ajaxBuild(treeObj);
          },


           makeGroupsList : function(data) { // uses the data returned by the ajax call (array of objects) from the checkbox onclick to make a checklist of groups
                  //alert('making groups list');
                  var groupDiv = document.getElementById('configGroups');
                  var dataLength = data.length;
                  var idCounter = 4;  //continue the numbering of the ids from 4 (main checkboxes are 1-3). This allows us to disable/enable them
                  if (dataLength === 0) {
                      var emptyLabel = document.createTextNode(amVariables.nogroups);
                      groupDiv.appendChild(emptyLabel);
                  }
                  for(var v=0;v<dataLength;v++) {

                           var box = '';
                           try{
                                  box = document.createElement('<input type="checkbox" name="showhide" />');
                              }catch(err){
                                  box = document.createElement('input');
                              }
                                  box.setAttribute('type','checkbox');
                                  box.setAttribute('name','groups');
                          //var box = document.createElement('input');
                                  //box.type = 'checkbox';
                                  box.id = 'config'+idCounter;
                                  //box.name = 'groups';
                                  box.value = data[v].id;
                                  groupDiv.appendChild(box);
                          if (data[v].display == 'true') {
                                  //alert('display true');
                                  box.checked = true;
                          } else {
                                  box.checked = false;
                          }

                          // silly IE6 hack
                          //groupDiv.innerHTML = groupDiv.innerHTML;

                          box.onclick =function() { AJAXmarking.boxOnClick();};
                          //box.onClick = function() { var temp = this.boxClick(); temp(); };

                          var label = document.createTextNode(data[v].name);
                          groupDiv.appendChild(label);
                          var breaker = document.createElement('br');
                          groupDiv.appendChild(breaker);
                          idCounter++;
                  }
                  AJAXmarking.config.icon.removeAttribute('class', 'loaderimage');
                  AJAXmarking.config.icon.removeAttribute('className', 'loaderimage');
                  //AJAXmarking.removeNodes(config.icon);
                  //AJAXmarking.config.icon.innerHTML = '';  //lose loading icon
                  AJAXmarking.enableRadio(); //re-enable the checkboxes
          },




       // this.ajaxCallback = ajaxCallback;
      // constructor stuff - makes a new tree where its told to

     


  ////////////////////////////////////////////////////////////////////////////////////////////////
  // function to alter a node's label with a new count once the children are removed or reloaded
  ////////////////////////////////////////////////////////////////////////////////////////////////

  countAlter : function (newNode, newCount) {
          var name = newNode.data.name;
          var newLabel = name+' ('+newCount+')';
          newNode.data.count = newCount;
          newNode.label = newLabel;
  },


  /**
   * on click function for the groups check boxes on the config screen. clicking sets or unsets
   * a particular group for display.
   */

 boxOnClick : function() {
     //alert('boxonclick');
                var form = document.getElementById('configshowform');
               
                window.AJAXmarking.disableRadio();

                // hacky IE6 compatible fix
                for (c=0;c<form.childNodes.length;c++) {
                   switch (form.childNodes[c].name) {
                       case 'course':
                           var course = form.childNodes[c].value;
                           break;
                       case 'assessmenttype':
                           var assessmentType = form.childNodes[c].value;
                           break;
                       case 'assessment':
                           var assessment = form.childNodes[c].value;
                           break;
                    }
                }

                // need to construct a space separated list of group ids.
                var groupIds = '';
                var groupDiv = document.getElementById('configGroups');
                var groups = groupDiv.getElementsByTagName('input');
                var groupsLength = groups.length;
                //alert(groupsLength);
                for (var a=0;a<groupsLength;a++) {

                        if (groups[a].checked === true) {
                                //alert('true');
                                groupIds += groups[a].value+' ';
                        }

                }

                if (groupIds === '') { // there are no checked boxes
                        groupIds = 'none'; //don't leave the db field empty as it will cause confusion between no groups chosen and first time we set this.
                }

                var reqUrl = amVariables.wwwroot+'/blocks/ajax_marking/ajax.php?id='+course+'&assessmenttype='+assessmentType+'&assessmentid='+assessment+'&type=config_group_save&userid='+amVariables.userid+'&showhide=2&groups='+groupIds+'';

                var request = YAHOO.util.Connect.asyncRequest('GET', reqUrl, AMajaxCallback);
                //alert('data save');
        },

  /**
   * this function is called every 100 milliseconds once the assignment pop up is called
   * and tries to add the onclick handlers until it is successful. There are a few extra
   * checks in the following functions that appear to be redundant but which are
   * necessary to avoid errors. The part of /mod/assignment/lib.php at line 509 tries to
   * update the main window with $this->update_main_listing($submission). This fails because
   * there is no main window with the submissions table as there would have been if the pop
   * up had been generated from the submissions grading screen. To avoid the errors,
   */

  // NOTE: the offset system for saveandnext depends on the sort state having been stored in the $SESSION variable when the grading screen was accessed
  // (which may not have happened, as we are not coming from the submissions.php grading screen or may have been a while ago).
  // The sort reflects the last sort mode the user asked for when ordering the list of pop-ups, e.g. by clicking on the firstname column header.
  // I have not yet found a way to alter this variable using javascript - ideally, the sort would be the same as it is in the list presented in the marking block.
  // until a work around is found, the save and next function is be a bit wonky, sometimes showing next when there is only one submission, so I have hidden it.

  assignmentOnLoad: function(me, userid) {
      var els ='';
      var els2 = '';
      var els3 = '';
      AJAXmarking.t++;
      //alert ('fired');

      // when th DOM is ready, add the onclick events and hide the other buttons
      if (AJAXmarking.windowobj.document) {
          if (AJAXmarking.windowobj.document.getElementsByName) {

              els = AJAXmarking.windowobj.document.getElementsByName('submit');

              if (els.length > 0) { // the above line will not return anything until the pop up is fully loaded

                  // To keep the assignment javascript happy, we need to make some divs for it to copy the
                  // grading data to, just as it would if it was called from the main submission grading screen.
                  // Line 710-728 of /mod/assignment/lib.php can't be dealt with easily, so there will
                  // be an error if outcomes are in use, but hopefully, that won't be so frequent.
                  // TODO see if there is a way to grab the outcome ids from the pop up and make divs using them that
                  // will match the ones that the javascript is looking for
                  var div = document.createElement('div');
                  div.setAttribute('id', 'com'+userid);
                  //div.setAttribute('display', 'none');
                  div.style.display = 'none';


                  var textArea = document.createElement('textarea');
                  textArea.setAttribute('id', 'submissioncomment'+userid);
                  //textArea.setAttribute('display', 'none');
                  textArea.style.display = 'none';
                  textArea.setAttribute('rows', "2");
                  textArea.setAttribute('cols', "20");
                  div.appendChild(textArea);
                  window.document.getElementById('javaValues').appendChild(div);

                  var div2 = document.createElement('div');
                  div2.setAttribute('id', 'g'+userid);
                  //div2.setAttribute('display', 'none');
                  div2.style.display = 'none';
                  window.document.getElementById('javaValues').appendChild(div2);

                  var textArea2 = document.createElement('textarea');
                  textArea2.setAttribute('id', 'menumenu'+userid);
                  //textArea2.setAttribute('display', 'none');
                  textArea2.style.display = 'none';
                  textArea2.setAttribute('rows', "2");
                  textArea2.setAttribute('cols', "20");
                  window.document.getElementById('g'+userid).appendChild(textArea2);

                  var div3 = document.createElement('div');
                  div3.setAttribute('id', 'ts'+userid);
                  //div3.setAttribute('display', 'none');
                  div3.style.display = 'none';
                  window.document.getElementById('javaValues').appendChild(div3);


                  var div4 = document.createElement('div');
                  div4.setAttribute('id', 'tt'+userid);
                  //div4.setAttribute('display', 'none');
                  div4.style.display = 'none';
                  window.document.getElementById('javaValues').appendChild(div4);


                  var div5 = document.createElement('div');
                  div5.setAttribute('id', 'up'+userid);
                  //div5.setAttribute('display', 'none');
                  div5.style.display = 'none';
                  window.document.getElementById('javaValues').appendChild(div5);


                  var div6 = document.createElement('div');
                  div6.setAttribute('id', 'finalgrade_'+userid);
                  //div6.setAttribute('display', 'none');
                  div6.style.display = 'none';
                  window.document.getElementById('javaValues').appendChild(div6);

                  // now add onclick
                  els[0]["onclick"] = new Function("return AJAXmarking.saveChangesAJAX(-1, AJAXmarking.main, '"+me+"', false); "); // IE
                  els2 = AJAXmarking.windowobj.document.getElementsByName('saveandnext');

                  if (els2.length > 0) {
                          els2[0].style.display = "none";
                          els3 = AJAXmarking.windowobj.document.getElementsByName('next');
                          els3[0].style.display = "none";
                  }

                  window.clearInterval(AJAXmarking.timerVar); // cancel the loop for this function

              }
          }
      }
  },


  //////////////////////////////////////////////////////////////////
  // workshop pop up stuff
  //////////////////////////////////////////////////////////////////

  ///////////////////////////////////////////////////////////////////////////////////
  // function to add workshop onclick stuff and shut the pop up after its been graded.
  // the pop -up goes to a redirect to display the grade, so we have to wait until
  // then before closing it so that the grade is processed properly.
  ///////////////////////////////////////////////////////////////////////////////////

  // note: this looks odd because there are 2 things that needs doing, one after the pop up loads (add onclicks)and one after it goes to its redirect
  // (close window).it is easier to check for a fixed url (i.e. the redirect page) than to mess around with regex stuff to detect a dynamic url, so the
  // else will be met first, followed by the if. The loop will keep running whilst the pop up is open, so this is not very elegant or efficient, but
  // should not cause any problems unless the client is horribly slow. A better implementation will follow sometime soon.

 workshopOnLoad : function (me, parent, course) {
          var els ='';
          if (typeof AJAXmarking.windowobj.frames[0] != 'undefined') { //check that the frames are loaded - this can vary according to conditions
              if (AJAXmarking.windowobj.frames[0].location.href != amVariables.wwwroot+'/mod/workshop/assessments.php') {
              // this is the early stage, pop up has loaded and grading is occurring
                      // annoyingly, the workshop module has not named its submit button, so we have to get it using another method as the 11th input
                          els = AJAXmarking.windowobj.frames[0].document.getElementsByTagName('input');
                          if (els.length == 11) {
                                  els[10]["onclick"] = new Function("return AJAXmarking.saveChangesAJAX('/mod/workshop/assessments.php', AJAXmarking.main, '"+me+"', true);"); // IE
                                
                                  window.clearInterval(AJAXmarking.timerVar);	// cancel loop
                              
                          }
                  }
          }
  },

  /**
  * function to add onclick stuff to the forum ratings button. This button also has no name or id so we
  * identify it by getting the last tag in the array of inputs. The function is triggered on an interval
  * of 1/2 a second until it manages to close the pop up after it has gone to the confirmation page
  */

  forumOnLoad : function (me) {
      var els ='';
      var name = navigator.appName;
      // first, add the onclick if possible
      if (typeof AJAXmarking.windowobj.document.getElementsByTagName('input') != 'undefined') { // window is open with some input. could be loading lots though.
          els = AJAXmarking.windowobj.document.getElementsByTagName('input');

          if (els.length > 0) {
              var key = els.length -1;
              if (els[key].value == amVariables.forumSaveString) { // does the last input have the 'send in my ratings string as label, showing that all the rating are loaded?

                     els[key]["onclick"] = new Function("return AJAXmarking.saveChangesAJAX('/mod/forum/rate.php', AJAXmarking.main, '"+me+"');"); // IE
                 
                  window.clearInterval(AJAXmarking.timerVar); // cancel loop for this function
                  
              }
          }
      }
  },

/**
 * adds onclick stuff to the quiz popup
 */
  quizOnLoad : function (me) {
      var els = '';
      var lastButOne = '';
     // t = t + 1; //what was this for?

     // alert('onload');
      if (typeof AJAXmarking.windowobj.document.getElementsByTagName('input') != 'undefined') { // window is open with some input. could be loading lots though.
          els = AJAXmarking.windowobj.document.getElementsByTagName('input');

          if (els.length > 14) { // there is at least the DOM present for a single attempt, but if the student has made a couple of attempts,
                                  // there will be a larger window.
              lastButOne = els.length - 1;
              //alert(els[lastButOne].value);
              if (els[lastButOne].value == amVariables.quizSaveString) {
//alert ('adding onclick');
                  // the onclick carries out the functions that are already specified in lib.php, followed by the function to update the tree
                 //var name = navigator.appName;
                 // if (name == "Microsoft Internet Explorer") {
                      els[lastButOne]["onclick"] = new Function("return AJAXmarking.saveChangesAJAX('/mod/quiz/report.php', AJAXmarking.main, '"+me+"'); "); // IE
                  //} else {
                         // els[lastButOne].setAttribute("onClick", "AJAXmarking.saveChangesAJAX('/mod/quiz/report.php', AJAXmarking.main, '"+me+"')"); // Mozilla etc.
                  //}
                  window.clearInterval(AJAXmarking.timerVar); // cancel the loop for this function
                
              }
          }
      }
  },

/**
 * adds onclick stuff to the journal pop up elements once they are ready
 */
journalOnLoad :   function (me) {
      var els ='';
      // first, add the onclick if possible
      if (typeof AJAXmarking.windowobj.document.getElementsByTagName('input') != 'undefined') { // window is open with some input. could be loading lots though.

          els = AJAXmarking.windowobj.document.getElementsByTagName('input');

          if (els.length > 0) {
               
              var key = els.length -1;
              
              if (els[key].value == amVariables.journalSaveString) { // does the last input have the 'send in my ratings' string as label, showing that all the rating are loaded?
                  
                 // els[key].setAttribute("onClick", "return AJAXmarking.saveChangesAJAX('/mod/journal/report.php', AJAXmarking.main, '"+me+"')"); // mozilla and all other good browsers
                  
                  els[key]["onclick"] = new Function("return AJAXmarking.saveChangesAJAX('/mod/journal/report.php', AJAXmarking.main, '"+me+"');"); // IE

                  window.clearInterval(AJAXmarking.timerVar); // cancel loop for this function

              }
          }
      }
  },


  /**
   * function that waits till the pop up has a particular location,
   * i.e. the one it gets to when the data has been saved, and then shuts it.
   */

  afterLoad : function (loc) { 
     
      if (!AJAXmarking.windowobj.closed) {

          if (AJAXmarking.windowobj.location.href == amVariables.wwwroot+loc) {
              setTimeout('AJAXmarking.windowobj.close()', 1000);
              return;
          }

      } else if (AJAXmarking.windowobj.closed) {
          return;
      } else {
          setTimeout(AJAXmarking.afterLoad(loc), 1000);
          return;
      }
  },


  /**
   *need to find the size of the window so the dark div is not too small
   * obsolete.
   */
/*
  alertSize : function () { // http://www.howtocreate.co.uk/tutorials/javascript/browserwindow
    var  myHeight = 0;
    if( typeof( window.innerWidth ) == 'number' ) {
      //Non-IE
      myHeight = window.innerHeight;
    } else if( document.documentElement && ( document.documentElement.clientWidth || document.documentElement.clientHeight ) ) {
      //IE 6+ in 'standards compliant mode'
      myHeight = document.documentElement.clientHeight;
    } else if( document.body && ( document.body.clientWidth || document.body.clientHeight ) ) {
      //IE 4 compatible
      myHeight = document.body.clientHeight;
    }
    return myHeight;
  },
*/
/**
 * IE seems not to want to expand the block when the tree becomes wider.
 * This provides a one-time resizing so that it is a bit bigger
 */

   ie_width : function () {
      if (/MSIE (\d+\.\d+);/.test(navigator.userAgent)){
     
          var el = document.getElementById('treediv');
          var width = el.offsetWidth;
          // set width of main content div to the same as treediv
          var contentDiv = el.parentNode;
          contentDiv.style.width = width;
      }
  },

  /**
   * Builds the greyed out panel for the config overlay
   */

  greyBuild : function() {

      if (!AJAXmarking.greyOut) {
           AJAXmarking.greyOut =
                new YAHOO.widget.Panel("greyOut",
                    { width:"470px",
                      height:"530px",
                      fixedcenter:true,
                      close:true,
                      draggable:false,
                      zindex:110,
                      modal:true,
                      visible:false,
                      iframe: true
                    }
                );

            var headerText = amVariables.headertext+' '+amVariables.fullname;
            AJAXmarking.greyOut.setHeader(headerText);

            var bodyText = "<div id='configIcon' class='AMhidden'></div><div id='configStatus'></div><div id='configTree'></div><div id='configSettings'><div id='configInstructions'>"+amVariables.instructions+"</div><div id='configCheckboxes'><form id='configshowform' name='configshowform'></form></div><div id='configGroups'></div></div>";
            
            AJAXmarking.greyOut.setBody(bodyText);
            document.body.className += ' yui-skin-sam';

            AJAXmarking.greyOut.beforeHideEvent.subscribe(function() {
                 AJAXmarking.refreshTree(AJAXmarking.main);
            });
           
            AJAXmarking.greyOut.render(document.body);

            AJAXmarking.greyOut.show();

            AJAXmarking.config = new AJAXmarking.AJAXtree('configTree', 'configIcon', 'configStatus', true);
            AJAXmarking.ajaxBuild(AJAXmarking.config);
           
            AJAXmarking.config.icon.setAttribute('class', 'loaderimage');
            AJAXmarking.config.icon.setAttribute('className', 'loaderimage');

        } else {

          AJAXmarking.greyOut.show();
          AJAXmarking.clearGroupConfig();
          AJAXmarking.refreshTree(AJAXmarking.config);
        }
   },

  /**
   * the onclick for the radio buttons in the config screen.
   * if show by group is clicked, the groups thing pops up. If another one is, the groups thing is hidden.
   */

  showHideChanges : function(checkbox) {
      // if its groups, show the groups by getting them from the course node?

      var showHide = '';

      //empty the groups area
      var groupDiv = document.getElementById('configGroups');
      while (groupDiv.firstChild) {
          groupDiv.removeChild(groupDiv.firstChild);
      }

      switch (checkbox.value) {
              case 'groups': //need to set the type of this assessment to 'show groups' and get the groups stuff.

                    showHide = 2;
                    //get the form div to be able to read the values
                    var form = document.getElementById('configshowform');

                     // silly IE6 bug fix
                     for (c=0;c<form.childNodes.length;c++) {
                         switch (form.childNodes[c].name) {
                             case 'course':
                                 var course = form.childNodes[c].value;
                                 break;
                             case 'assessmenttype':
                                 var assessmentType = form.childNodes[c].value;
                                 break;
                             case 'assessment':
                                 var assessment = form.childNodes[c].value;
                                 break;
                          }
                      }
                      var url = amVariables.wwwroot+'/blocks/ajax_marking/ajax.php?id='+course+'&assessmenttype='+assessmentType+'&assessmentid='+assessment+'&type=config_groups&userid='+amVariables.userid+'&showhide='+showHide+'';
                      var request = YAHOO.util.Connect.asyncRequest('GET', url, AMajaxCallback);
                      break;
              case 'show':

                      AJAXmarking.configSet(1);
                      break;
              case 'hide':

                      AJAXmarking.configSet(3);
                      break;

      } // end switch
      AJAXmarking.disableRadio();

  },



  /**
   * called from showhidechanges() to set the showhide value of the config items
   */

  configSet : function (showHide) {
          var form = document.getElementById('configshowform');
         
          var len = form.childNodes.length;
        
         // silly hack to fix the way IE6 will not retrieve data from an input added using appendChild using form.assessment.value
          for(b=0; b<len; b++) {

              switch (form.childNodes[b].name) {
                  case 'assessment':
                      var assessmentValue = form.childNodes[b].value;
                      break;
                  case 'assessmenttype':
                      var assessmentType = form.childNodes[b].value;
                      break;

              }
          }
          var url = amVariables.wwwroot+'/blocks/ajax_marking/ajax.php?id='+assessmentValue+'&type=config_set&userid='+amVariables.userid+'&assessmenttype='+assessmentType+'&assessmentid='+assessmentValue+'&showhide='+showHide+'';
         
          var request = YAHOO.util.Connect.asyncRequest('GET', url, AMajaxCallback);
  },

  clearGroupConfig : function() {
    
        
          AJAXmarking.removeNodes(document.getElementById('configshowform'));
       
          AJAXmarking.removeNodes(document.getElementById('configInstructions'));
        
          AJAXmarking.removeNodes(document.getElementById('configGroups'));
        
          return true;
  },
  removeNodes: function (el) {
      if (el.hasChildNodes()) {
         
          while (el.hasChildNodes()) {
              el.removeChild(el.firstChild);
          }
      }
  }
  
 


}; // end main class


/**
 * Callback object for the AJAX call, which
 * fires the correct function. Doesn't work when part of the main class.
 */
var  AMajaxCallback =
  {
    cache    : false,
    success  : AJAXmarking.AJAXsuccess,
    failure  : AJAXmarking.AJAXfailure,
    argument : 1200

  };


function AMinit() {
   
    if ( document.location.toString().indexOf( 'https://' ) != -1 ) {
        amVariables.wwwroot = amVariables.wwwroot.replace('http:', 'https:');
    }
    // the context menu needs this for the skinning
    document.body.className += ' yui-skin-sam';
    AJAXmarking.main = new AJAXmarking.AJAXtree('treediv', 'mainIcon', 'status', false);

    AJAXmarking.ajaxBuild(AJAXmarking.main);
    
}
// this stuff needs to stay at the end. used to be in the main php file with a defer thing but I think it broke the xhtml stuff
AMinit();
	

