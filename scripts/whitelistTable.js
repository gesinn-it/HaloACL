/*
 * Copyright (C) Vulcan Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * @file
 * @ingroup HaloACL_UI_Script
 */
/**
 * This file contains javascript used for the whitelist datatable
 *
 * @author B2browse/Patrick Hilsbos, Steffen Schachtler
 * Date: 07.10.2009
 *
 */
/**
 * Description of HACL_AjaxConnector
 *
 * @author hipath
 */

/**
 *  creates whitelist datatable
 *  @param  target-div-id
 *  @param  panelid / identifier
 *
 */

YAHOO.haloacl.whitelistTable = function(divid,panelid) {

    // custom defined formatter
    this.mySelectFormatter = function(elLiner, oRecord, oColumn, oData) {
        var checkedFromTree = false;
        if(YAHOO.haloacl.whitelistClicks.indexOf(oRecord._oData.name) != -1){
            checkedFromTree = true;
        }

        if(oData == true || checkedFromTree == true){
            elLiner.innerHTML = "<input onClick='YAHOO.haloacl.whitelistCheck(this);' type='checkbox' checked='' class='"+divid+"_users' name='"+oRecord._oData.name+"' />";
        }else{
            elLiner.innerHTML = "<input onClick='YAHOO.haloacl.whitelistCheck(this);' type='checkbox' class='"+divid+"_users' name='"+oRecord._oData.name+"' />";
        }
    };
    

    this.myNameFormatter = function(elLiner, oRecord, oColumn, oData) {
        elLiner.innerHTML = "<span class='"+divid+"_usersgroups' groups=\""+oRecord._oData.groups+"\">"+oRecord._oData.name+"</span>";
    };

    // building shortcut for custom formatter
    //YAHOO.widget.DataTable.Formatter.mySelect = this.mySelectFormatter;
    //YAHOO.widget.DataTable.Formatter.myName = this.myNameFormatter;

    var myColumnDefs = [ // sortable:true enables sorting
    {
        key:"name",
        label:gHACLLanguage.getMessage('name'),
        sortable:false,
        formatter:this.myNameFormatter
    },
    {
        key:"checked",
        label:gHACLLanguage.getMessage('delete'),
        formatter:this.mySelectFormatter
    }
    ];

    // datasource for this userdatatable
    var myDataSource = new YAHOO.util.DataSource("?action=ajax");
    myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSON;
    myDataSource.connMethodPost = true;
    myDataSource.responseSchema = {
        resultsList: "records",
        fields: [
        {
            key:"id",
            parser:"number"
        },
        {
            key:"name"
        },
        {
            key:"checked"
        }
        ],
        metaFields: {
            totalRecords: "totalRecords" // Access to value in the server response
        }
    };

    // our customrequestbuilder (attached to the datasource)
    // this requestbuilder, builds a valid mediawiki-ajax-request
    var customRequestBuilder = function(oState, oSelf) {
        // Get states or use defaults
        oState = oState;
        var totalRecords = oState.pagination.totalRecords;
        var sort = (oState.sortedBy) ? oState.sortedBy.key : null;
        var dir = (oState.sortedBy && oState.sortedBy.dir == YAHOO.widget.DataTable.CLASS_DESC) ? "desc" : "asc";
        var startIndex = oState.pagination.recordOffset;
        var results = oState.pagination.rowsPerPage;
        /* make the initial cache of the form data */

        if(myDataTable.query == null){
            myDataTable.query = '';
        }

        var filter = $('datatable_filter_'+myDataTable.panelid).value;
        
        return "rs=haclGetUsersForUserTable&rsargs[]="
        +myDataTable.query+"&rsargs[]="+sort
        +"&rsargs[]="+dir
        +"&rsargs[]="+startIndex
        +"&rsargs[]="+results
        +"&rsargs[]="+filter;
    };



    // whitelisttable configuration
    var myConfigs = {
        initialRequest: "rs=haclGetWhitelistPages&rsargs[]=all&rsargs[]=name&rsargs[]=asc&rsargs[]=0&rsargs[]=5&rsargs[]=", // Initial request for first page of data
        dynamicData: true, // Enables dynamic server-driven data
        sortedBy : {
            key:"name",
            dir:YAHOO.widget.DataTable.CLASS_ASC
        }, // Sets UI initial sort arrow
        //    paginator: myPaginator,
        generateRequest:customRequestBuilder
    };

    // instanciating datatable
    var myDataTable = new YAHOO.widget.DataTable(divid, myColumnDefs, myDataSource, myConfigs);

    // Update totalRecords on the fly with value from server
    myDataTable.handleDataReturnPayload = function(oRequest, oResponse, oPayload) {
        oPayload.totalRecords = oResponse.meta.totalRecords;
        if($('haloacl_whitelist_count') != null){
            $('haloacl_whitelist_count').innerHTML = oPayload.totalRecords;
        }
        return oPayload;
    }
    myDataTable.query = "";

    

    //YAHOO.util.Event.addListener(myDataTable,"initEvent",myDataTable.checkAllSelectedUsers());

    // function called from grouptree to update userdatatable on GroupTreeClick
    myDataTable.executeQuery = function(query){
        var oCallback = {
            success : myDataTable.onDataReturnInitializeTable,
            failure : myDataTable.onDataReturnInitializeTable,
            scope : myDataTable,
            argument : myDataTable.getState()
        };
        if(YAHOO.haloacl.debug) console.log("sending request");
        myDataSource.sendRequest('rs=haclGetWhitelistPages&rsargs[]='+query+'&rsargs[]=name&rsargs[]=asc&rsargs[]=0&rsargs[]=5&rsargs[]="', oCallback);
        if(YAHOO.haloacl.debug) console.log("reqeust sent");
    }

    myDataTable.subscribe("postRenderEvent",function(){
        handlePagination();
        
        
    });
    // setting up clickevent-handling
    return myDataTable;


   
};

// --------------------
// --------------------
// --------------------



