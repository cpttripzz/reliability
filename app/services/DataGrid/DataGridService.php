<?php

namespace App\Services\DataGrid;

use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use App\Models\Customer;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Zend\Json\Expr;
use Zend\Json\Json;
use Cpttripzz\JqGridExtra\Grid;

abstract class DataGridService
{
    protected $userService;
    protected $columnModel;

    /**
     * The column+
     * @return mixed
     */
    public function getColumnModel()
    {
        return $this->columnModel;
    }
    protected $query;
    protected $isRaw = false;
    protected $queryBindings = array();

    protected $grid;

    protected $caption;
    /**
     * array $jsonReader
     * json object for jqgrid to translate data passed to grid from api
     * http://www.trirand.com/jqgridwiki/doku.php?id=wiki:retrieving_data
     */
    protected $jsonReader = array('root' => 'data');
    /**
     * @var array with number of pages to show for jqgrid
     */
    protected $rowList = array(15, 30, 50);
    protected $height = '100%';
    protected $width = null;
    protected $url;
    protected $sortname;
    /**
     * @var array of fields to send to api when grid is submitted
     */
    protected $postDataFields;

    protected $additionalPostData = array('export'=>'csv');
    /**
     * @var array
     * custom nav buttons to show in footer of grid
     * http://www.trirand.com/jqgridwiki/doku.php?id=wiki:custom_buttons
     */
    protected $customNavButtons  = array('exportCsv');
    const SEARCH_TYPE_EQUALS = 'EQ';
    const SEARCH_TYPE_LIKE = 'LK';
    const SEARCH_TYPE_DATE_RANGE = 'daterange';

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->grid = new Grid();
        $this->grid->setRowsPerPage(Request::input('rows', 15))
            ->setHeight($this->height)
            ->setWidth($this->width)
            ->setRowList($this->rowList)
            ->setJsonReader($this->jsonReader)
            ->setDataUrl($this->url)
            ->setSortName($this->sortname)
            ->setPostDataFields($this->postDataFields);

        $this->grid->setCustomNavButtons($this->getCustomNavButtons());
        $this->filterColumnsByDB();
        $this->setGridColumns();

    }

    protected function getCustomNavButtons()
    {
        $customNavButtons = [
            'exportCsv' => $this->getCsvButton(),
            'deleteRow' => $this->getDeleteRowButton(),
            'changePassword' => $this->getChangePasswordButton(),
            'createApiKey' => $this->getGenerateApiKeyButton()
        ];
        if(!empty($this->customNavButtons)){
            foreach ($customNavButtons as $navButtonName => $arrNavButton) {

                $found = false;
                foreach($this->customNavButtons as $selectedNavButton) {
                    if($navButtonName === $selectedNavButton){
                        $found = true;
                        break;
                    }

                }
                if(!$found){
                    unset ($customNavButtons[$navButtonName]);
                }
            }
        }
        return $customNavButtons;
    }

    protected function getDeleteRowButton()
    {
        return  array(
            'caption' => 'Delete Row(s)',
            'buttonicon' => ' ui-icon-trash',
            'position' => 'last',
            'onClickButton' => new Expr('
                    function(){
                        setTimeout(function () {

                            '.$this->getOnclickDeleteRow() .'

                        }, 100);

                    }
                ')
        );
    }
    protected function getGenerateApiKeyButton()
    {
        return  array(
            'caption' => 'Generate API Key',
            'buttonicon' => ' ui-icon-key',
            'position' => 'last',
            'onClickButton' => new Expr('
                    function(){
                        setTimeout(function () {

                            '.$this->getOnclickCreateApiKey() .'

                        }, 100);
                    }
                ')
        );
    }

    protected function getChangePasswordButton()
    {
        return  array(
            'caption' => 'Change password',
            'buttonicon' => '  ui-icon-person',
            'position' => 'last',
            'onClickButton' => new Expr('
                    function(){
                        setTimeout(function () {

                            '.$this->getOnclickChangePassword() .'

                        }, 100);
                    }
                ')
        );
    }

    protected function getOnclickDeleteRow()
    {
        $js = <<<JS

        jQuery(function() {
            var ids = jQuery("{$this->grid->getGridIdentifier()}").jqGrid('getGridParam','selarrrow');
            if(ids.length === 0){
                toastr.warning('no rows selected');
            } else {
                jQuery( "#dialog-confirm-user-delete" ).dialog({
                  resizable: false,
                  height:140,
                  modal: true,
                  buttons: {
                            "Delete User(s)": function() {
                                    var data = {
                                        oper: 'del'
                                    };

                                    data.ids = ids;
                                    jQuery.ajax({
                                        method: 'post',
                                        url: "{$this->url}",
                                        data: data
                                    }).success(function() {
                                        toastr.success('users deleted');
                                        jQuery("{$this->grid->getGridIdentifier()}").trigger('reloadGrid');
                                        jQuery( "#dialog-confirm-user-delete" ).dialog( "close" );
                                    }).error(function(){
                                        toastr.warning('error: users not deleted');
                                    })
                            },
                            Cancel: function() {
                                jQuery( this ).dialog( "close" );
                            }
                  }
                });
            }
          });
JS;
        return $js;
    }

    protected function getOnclickChangePassword()
    {
        $js = <<<JS
jQuery(function () {
var dialog, form;
	var ids = jQuery("{$this->grid->getGridIdentifier()}").jqGrid('getGridParam', 'selarrrow');
	if (ids.length === 0) {
		toastr.warning('no rows selected');
	} else if (ids.length > 1) {
		toastr.warning('Only select one row at a time');
	} else {

		dialog = $("#dialog-form").dialog({
			autoOpen: false,
			height: 300,
			width: 350,
			modal: true,
			buttons: {
				"Change password": function () {
					var id = jQuery("{$this->grid->getGridIdentifier()}").jqGrid('getGridParam', 'selarrrow');
					var password = jQuery("#password").val();
					var data = {
                        oper: 'changePassword',
                        password: password,
                        id: id
                    };

                    jQuery.ajax({
                        method: 'post',
                        url: "{$this->url}",
                        data: data
                    }).success(function(response) {
                        if(response.success){
                                    toastr.success('Password changed successfully');
                                    jQuery("{$this->grid->getGridIdentifier()}").trigger("reloadGrid");
                                } else {
                                    jQuery.each(jQuery.parseJSON(response.message), function( index, value ) {
                                        toastr.error(value);
                                    });
                                    return false;
                                }

                        jQuery("{$this->grid->getGridIdentifier()}").trigger('reloadGrid');
                        dialog.dialog("close");
                    }).error(function(){
                        toastr.error('error: password not changed');
                    })
				},
				Cancel: function () {
					dialog.dialog("close");
				}
			},

			close: function () {
				form[0].reset();
			}
		});
		    form = dialog.find( "form" ).on( "submit", function( event ) {
              event.preventDefault();
            });
		dialog.dialog("open");
	}
});
JS;
        return $js;
    }

    protected function getOnclickCreateApiKey()
    {
        $js = <<<JS
jQuery(function () {
	var ids = jQuery("{$this->grid->getGridIdentifier()}").jqGrid('getGridParam', 'selarrrow');
	if (ids.length === 0) {
		toastr.warning('no rows selected');
	} else if (ids.length > 1) {
		toastr.warning('Only select one row at a time');
	} else {
        jQuery( "#dialog-confirm-generate-api-key" ).dialog({
                  resizable: false,
                  height:140,
                  modal: true,
                  buttons: {
                        "Generate API Key for user?": function() {
                                var data = {
                                    oper: 'generateApiKey'
                                };

                                data.ids = ids;
                                jQuery.ajax({
                                    method: 'post',
                                    url: "{$this->url}",
                                    data: data
                                }).success(function() {
                                    toastr.success('API key generated');
                                    jQuery("{$this->grid->getGridIdentifier()}").trigger('reloadGrid');
                                    jQuery( "#dialog-confirm-generate-api-key" ).dialog( "close" );
                                }).error(function(){
                                    toastr.warning('error: API Key not generated');
                                })
                        },
                        Cancel: function() {
                            jQuery( this ).dialog( "close" );
                        }
                  }
                });

	}
});
JS;
        return $js;
    }

    protected function getCsvButton()
    {
        return array(
            'caption' => 'Export CSV',
            'buttonicon' => 'ui-icon-arrowthickstop-1-s',
            'position' => 'last',
            'onClickButton' => new Expr('
                    function(){

                        '.
                $this->getGridFilterPostDataJS(false)
                .'

                    }
                ')
        );
    }

    /**
     * @param array $additionalPostData
     * @param bool $setGridParams
     * @return string
     * echo javascript vars from serverside filter model optionally passing in additional params, for now
     * this is only used for exporting csv.
     */
    public function getGridFilterPostDataJS($setGridParams = true)
    {

        $js = 'jQuery(function() {';
        $js .= 'var arrPostDataFields = {};' . "\n";
        $js .= 'var arrPostData = {}; ' . "\n";
        $postDataFields = $this->grid->getPostDataFields();
        if (!empty($postDataFields)) {
            foreach ($postDataFields as $field => $jsonValue) {
                $js .= 'arrPostDataFields["' . $field . '"]' . ' = ' . str_replace("%field%", $field, $jsonValue) . "\n";
            }
        }

        $js .= <<<JS
        var objPostData={};
        if(typeof arrPostDataFields !== 'undefined'){
            jQuery.each(arrPostDataFields, function( index, value ) {
              objPostData[index] = jQuery('#'+index).val();
            });
        }

JS;
        if ($setGridParams) {
            $js .= <<<JS
            jQuery("{$this->grid->gridIdentifier}").setGridParam({ postData:  null});
            jQuery("{$this->grid->gridIdentifier}").setGridParam({ postData:  objPostData});
JS;
        } else {
            $js .= <<<JS
            if(arrPostData){
                jQuery.each(arrPostData, function( index, value ) {
                  objPostData[index] = value;
                });
            }
            var postData = jQuery("{$this->grid->gridIdentifier}").jqGrid("getGridParam", "postData");
            var urlParams = [];
            obj = jQuery.extend( postData, objPostData );
            for(var k in obj){
                if(obj.hasOwnProperty(k) && !obj[k]){
                    delete obj[k];
                }
            }
            jQuery.each( obj, function(key, value) {
                if(jQuery.isArray(value) ){
                    jQuery.each( value, function(arrKey, arrValue) {
                        urlParams += "&"+key+"[]="+encodeURIComponent(arrValue);
                    });
                } else {
                    urlParams += "&"+key+"="+encodeURIComponent(value);
                }
            });


            jQuery.fileDownload("{$this->grid->dataUrl}?export=csv&"+urlParams)
            .done(function () {
                alert('I dont work');
            });
            return false; //this is critical to stop the click event which will trigger a normal file download!


JS;
        }
        $js.='});';
        return $js;
    }

    
    
    protected function getDateRangeSql($sqlOperator="AND", $field, $dateRange)
    {
        $dateRangeSql =" " .$sqlOperator . " " . $field . " BETWEEN {$this->setBoundParameter($dateRange['start']
            . ' 00:00:00')} AND {$this->setBoundParameter($dateRange['end'] .' 23:59:59')} ";
        return $dateRangeSql;
    }

    protected function filterColumnsByDB()
    {
        if(!$section = strtok(Route::currentRouteName(), '-')){
            return;
        }

        $sectionId = DB::table('section')->where('route', '=', $section)->pluck('id');
        if($this->userService->isAdmin()){
            return;
        }
        $userColumns = $this->userService->getUserScolumns($sectionId);
        if(empty($this->columnModel)){
            throw new \Exception('Insufficient Permission');
        }
        foreach($this->columnModel as $colKey =>$colModel) {
            $colFound = false;
            foreach ($userColumns as $scolumn) {
                $columnId = substr($scolumn['name'], strrpos($scolumn['name'], '.') + 1);
                if($columnId === $colModel['id']){
                    $colFound = true;
                    break;
                }
            }
            if(!$colFound){
                unset($this->columnModel[$colKey]);
            }
        }
    }
    /**
     * @return mixed
     */
    public function getQuery()
    {
        $parameters = $this->getParameters();
        $returnArray = array();
        try {
            $this->filterColumnsByDB();
        } catch (\Exception $e){
            return false;
        }
        if (!$this->isRaw){
            $this->setQueryColumnSelects($this->query);
            $this->setFilters($parameters, $this->query);
            $this->setSort($parameters, $this->query);
        }

        $rows = (!empty($parameters['rows'])) ? (int)$parameters['rows'] : 15;
        $page = (!empty($parameters['page'])) ? (int)$parameters['page'] : 1;

        $skip = $rows * ($page - 1);
        if ($this->isRaw) {
            if(empty($parameters['export'])) {
                $total = $this->getRawTotal();
            } else {
                $total = 0;
            }
            $this->setSort($parameters, $this->query, true);
            if(empty($parameters['export'])) {
                $this->query .= "LIMIT $rows OFFSET $skip";
            }
            $rawQueryBindings = (empty($this->rawQueryBindings)) ? array(): $this->rawQueryBindings;

            $returnData = DB::connection($this->connectionName)->select( DB::raw($this->query), $rawQueryBindings);
            if(!$this->userService->isAdmin()){
                $allowedFields = [];
                foreach($this->columnModel as $colmodel){
                    $allowedFields[] = (empty($colmodel['alias'])) ? $colmodel['id'] : $colmodel['alias'];
                }
                foreach($returnData as $returnDataKey =>$returnDataRow ) {
                    foreach($returnDataRow as $returnDataRowKey => $returnDataValue) {
                        if (!in_array($returnDataRowKey, $allowedFields)) {
                            unset($returnData[$returnDataKey][$returnDataRowKey] );
                        }
                    }
                }
            }
        } else {
            $total = $this->query->count();
            $this->query->skip($skip)->take($rows);
            $returnData = $this->query->get();
        }
        $returnArray['data'] = $returnData;
        $pages = ($total) ? (int)($total / $rows) + 1 : (int)($total / $rows);


        $returnArray['page'] = $page;
        $returnArray['records'] = $total;
        $returnArray['total'] = $pages;
        return $returnArray;
    }


    /**
     * @param mixed $query
     * @return DataGridService
     */
    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    public function getGrid()
    {
        return $this->grid;
    }

   public function setGrid($grid)
    {
        $this->grid = $grid;
        return $this;
    }

    public function  setGridColumns()
    {

        foreach ($this->columnModel as $arrColumnAttributes) {
            $searchOptions = $stype = $editType = $editOptions = null;
            if (!empty($arrColumnAttributes['searchType'])) {
                switch ($arrColumnAttributes['searchType']) {
                    case 'daterange':

                        $js = <<<JS
					    {dataInit:function(el){
                            $(el).daterangepicker({
                            dateFormat:'mm/dd/yy',
                            initialText : 'Select period...',
                            numberOfMonths : 2,
                            onClose: function(event) {
                               setTimeout(function () {
                                    jQuery("{$this->grid->getGridIdentifier()}")[0].triggerToolbar();
                                }, 100);

                           }
                         });
                        }
                       }
JS;
                        $searchOptions = new Expr($js);
                        break;
                    case 'select':
                        $stype = 'select';

                        $js = <<<JS
					    {
					        sopt: "eq",
					        value: "{$arrColumnAttributes['svalues']}",
                            dataInit:function(el){
                                $(el).val([]);
                                $(el).attr("multiple", true);
                                $(el).select2({multiple: true});
                            }
                       }
JS;
                        $searchOptions = new Expr($js);
                        break;
                }
            }
            if (!empty($arrColumnAttributes['edittype'])) {
                switch ($arrColumnAttributes['edittype']) {
                    case 'text':
                        $editType = $arrColumnAttributes['edittype'];
                        break;
                    case 'checkbox':
                        $editType = 'checkbox';
                        $editOptions = array('value' => $arrColumnAttributes['editvalues']);
                        break;
                    case 'multiselect':
                        $editType = 'select';
                        $jsString = Json::encode($arrColumnAttributes['editvalues'],false,array('enableJsonExprFinder' => true));
                        $js = <<<JS
					    {
                            multiple: true,
					        value: $jsString,
					        dataInit:function(el){
                                setTimeout(function () {
                                    $(el).select2();
                                }, 50);
                            }
                       }
JS;
                        $editOptions = new Expr($js);
                        break;
                }

            }
            $id = (empty($arrColumnAttributes['alias'])) ? $arrColumnAttributes['id'] : $arrColumnAttributes['alias'];
            $this->grid->addColumn(
                $arrColumnAttributes['label'], $id, $arrColumnAttributes['width'], null, $stype, $searchOptions,
                $editType,$editOptions
            );
        }
    }


    /**
     * @param $query
     */
    protected function filterQueryByUserCampaigns($query)
    {
        if (!$this->userService->isAdmin()) {
            $campaignIds = array();
            foreach ($this->userService->getUserCampaignIds() as $campaign) {
                $campaignIds[] = $campaign['id'];
            }
            $query->whereIn('campaigns.id', $campaignIds);
        }
    }
    public function getScolumnUniqueName($sectionTitle,$arrColumn)
    {
        return $sectionTitle . ' - ' . $arrColumn['table'] . '.' . $arrColumn['id'];
    }
    /**
     * @param $column
     * @return string
     */
    protected function getFullDbField($column)
    {
        return (!empty($column['id'])) ? $column['table'] . '.' . $column['id'] : null;
    }

    /**
     * @param $arrColumnAttributes
     * @return array
     */
    protected function getAlias($arrColumnAttributes)
    {
        $alias = (!empty($arrColumnAttributes['alias'])) ? ' AS ' . $arrColumnAttributes['alias'] : '';
        return $alias;
    }

    protected function setQueryColumnSelects($query)
    {
        foreach ($this->columnModel as $arrColumnAttributes) {
            $alias = $this->getAlias($arrColumnAttributes);
            $column = $this->getFullDbField($arrColumnAttributes);
            $query->addSelect($column . $alias);
        }
        $this->filterQueryByUserCampaigns($query);
    }

    /**
     * @param $parameters
     * @return array
     */
    protected function setSort($parameters, &$query, $useAlias = false)
    {
        if (!empty($parameters['sidx'])) {
            $sidx = $parameters['sidx'];
            foreach ($this->columnModel as $column) {
                if ((!empty($column['id'])) && $sidx === $column['id'] || (!empty($column['alias']) && $sidx === $column['alias'])) {
                    if ($useAlias) {
                        $orderField = $sidx;
                    } else {
                        $orderField = $this->getFullDbField($column);
                    }
                    break;
                }
            }
            $sord = (strtolower($parameters['sord']) ==='asc' ) ? 'asc' : 'desc';
            if(!empty($orderField) && !empty($sord)) {
                if ($this->isRaw) {
                    $query .= " ORDER BY $orderField $sord ";
                } else {
                    $query->orderBy($orderField, $sord);
                }
            }
        }
    }

    /**
     * @param $parameters
     */
    protected function setFilters($parameters, $query)
    {
        if (!(empty($parameters['_search'])) && $parameters['_search'] == 'true') {
            foreach ($parameters as $parameterName => $parameterValue) {
                foreach ($this->columnModel as $column) {
                    if ($parameterName === $column['id'] || (!empty($column['alias']) && $parameterName === $column['alias'])) {
                        $dbField = $this->getFullDbField($column);

                        if (empty($column['searchType'])) {
                            $searchType = self::SEARCH_TYPE_EQUALS;
                        } else {
                            $searchType = $column['searchType'];
                        }
                        switch ($searchType) {
                            case self::SEARCH_TYPE_EQUALS:
                                $query->where($dbField, '=', $parameterValue);
                                break;
                            case self::SEARCH_TYPE_LIKE:
                                $query->where($dbField, ' LIKE ', '"%' . $parameterValue . '%"');
                                break;
                            case self::SEARCH_TYPE_DATE_RANGE:
                                try {
                                    $jsonParams = Json::decode($parameterValue);

                                    $query->whereBetween($this->getFullDbField($column),
                                        array($jsonParams->start . ' 00:00:00', $jsonParams->end . ' 23:59:59')
                                    );
                                } catch (\Exception $e) {

                                }
                                break;
                        }

                    }
                }
            }
        }
    }

    /**
     * @param $arrColumnAttributes
     * @param $query
     */
    protected function addAliasedColumn($arrColumnAttributes, $query, $rawFunction = false, $addNull = false)
    {
        $alias = $this->getAlias($arrColumnAttributes);
        $column = $this->getFullDbField($arrColumnAttributes);
        if ($rawFunction) {
            if ($addNull) {
                $query->addSelect(DB::raw("NULL $alias)"));
            } else {
                $query->addSelect(DB::raw("$rawFunction($column) $alias"));
            }
        } else {
            $query->addSelect($column . $alias);
        }
    }

    /**
     * @param $query
     * @param $firstRun
     * @return mixed
     */
    protected function processSubQueries($query, $firstRun)
    {
        $arrColumnTypesToIgnore = array('calculation');
        foreach ($this->columnModel as $arrColumnAttributes) {
            if (empty($arrColumnAttributes['type'])) {
                $this->addAliasedColumn($arrColumnAttributes, $query);
            } else {
                if (!(in_array($arrColumnAttributes['type'], $arrColumnTypesToIgnore))) {
                    if ($firstRun) {
                        $this->addAliasedColumn($arrColumnAttributes, $query, 'count');
                    } else {
                        $this->addAliasedColumn($arrColumnAttributes, $query, null, true);
                    }
                    $firstRun = !$firstRun;
                }
            }
        }
        $this->filterQueryByUserCampaigns($query);
    }

    /**
     * @return mixed
     */
    protected function getParameters()
    {
        $parameters = Request::all();
        return $parameters;
    }

    /**
     * @param string $query
     * @return int
     */
    protected function getRawTotal()
    {
        /**
         * TODO find a less hacky way to do this.
         */
        if($this->connectionName == 'ott'){
            $total = User::selectRaw('COUNT(*) AS total')
                ->from(DB::raw(' ( ' . $this->query . ' ) AS t2 '))
                ->setBindings($this->rawQueryBindings)
                ->get();
        } else {
            $total = Customer::selectRaw('COUNT(*) AS total')
                ->from(DB::raw(' ( ' . $this->query . ' ) AS t2 '))
                ->setBindings($this->rawQueryBindings)
                ->get();
        }
        $total = $total->toArray();
        $total = $total[0]['total'];
        return $total;
    }

    /**
     * @param $boundParamValue
     * @return mixed
     */
    protected function setBoundParameter($boundParamValue)
    {
        $paramBindingName = $this->nextQueryBinding();
        $this->rawQueryBindings[$paramBindingName] = $boundParamValue;
        return ':'.$paramBindingName;
    }

}