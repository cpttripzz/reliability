<?php
/**
 * Report Service for queries that are not supported by Laravels ORM
 * Or are way too complex to try and get working if technically it might possible
 * Also if you try to use the orm with ->groupBy() you are gonna have a bad time,
 * Just a heads-up
 *
 * User: zach
 * Date: 19/04/15
 * Time: 14:49
 *
 */

namespace App\Services\DataGrid;

use App\Models\Customer;
use App\Services\UserService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Services\DataGrid\DataGridService;
use Illuminate\Support\Facades\Validator;
use Zend\Json\Json;
use Zend\Json\Expr;

abstract class RawDataReportService extends DataGridService
{
    protected $userService;
    /**
     * @var array of fields to submit to api when grid submit is triggered
     */
    protected $rawQueryParamFields;
    /**
     * @var array of fields that are allowed to filter grid with configuration to handle various
     * logic. Can be either from the grid column model or from other fields such as campaign filters \
     * etc that can not be in grid as the summary data is from multiple campaigns
     */

    protected $rawQueryParamFilters = array();
    protected $postDataFields = array();

    protected $rawQueryBindings = array();

    protected function nextQueryBinding()
    {
        return 'param_' .count($this->rawQueryBindings);
    }
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->loadRawQueryParams();
        $this->query = $this->getRawQuery($this->getRawQueryParams());
        $this->isRaw = true;
        parent::__construct($this->userService);
    }

    public function loadRawQueryParams()
    {
        foreach($this->columnModel as $colModel)
        {
            if(!empty($colModel['queryParamField'])){
                if(!is_array($colModel['queryParamField'])){
                    $dbField = array('field' => $colModel['id'], 'dbField' => $colModel['table'] .'.' . $colModel['id']);

                } else {
                    $dbField = $colModel['queryParamField'];
                }
                    $this->rawQueryParamFields[] = $dbField;

            }
        }
    }

    public function getRawQueryParams()
    {
        $parameters = $this->getParameters();
        $rawQueryParams = array();
        if(!empty($this->rawQueryParamFields)) {
            foreach ($this->rawQueryParamFields as $rawQueryParam) {
                if (!empty($rawQueryParam['defaultValue']) && empty($parameters[$rawQueryParam['field']])) {
                    $parameters[$rawQueryParam['field']] = $rawQueryParam['defaultValue'];
                }
                foreach ($parameters as $parameterField => $parameterValue) {


                    if ($parameterField === $rawQueryParam['field']) {
                        $fieldValue = null;

                        $parameterValue = $parameters[$rawQueryParam['field']];
                        if (empty($parameterValue)) {
                            break;
                        }
                        if (!empty($rawQueryParam['type'])) {
                            switch ($rawQueryParam['type']) {
                                case 'dateRange':
                                    try {
                                        $fieldValue = Json::decode($parameterValue, Json::TYPE_ARRAY);
                                    } catch (\Exception $e) {

                                    }
                                    break;
                                case 'select':
                                    $fieldValue = array($parameterValue);
                                    break;
                                case 'multiselect':

                                    $fieldValue = $parameterValue;
                                    if (empty($fieldValue)) {
                                        break 2;
                                    }
                                    if (!is_array($fieldValue)) {
                                        $fieldValue = array($fieldValue);
                                    }
                                    break;
                            }
                        } else {
                            $fieldValue = $parameterValue;
                        }
                        $rawQueryParams[$rawQueryParam['field']] = $fieldValue;
                        break;
                    }
                }

            }
        }

        return $rawQueryParams;
    }

    public function renderRawQueryParamFilters($arrRawQueryParamFiltersParams=array())
    {
        $returnString = '';
        foreach ($this->rawQueryParamFilters as $rawQueryParamFiltersName => $rawQueryParamFiltersClosure) {
            $params = null;
            foreach ($arrRawQueryParamFiltersParams as $arrRawQueryParamFiltersParamName => $arrRawQueryParamFiltersValue) {
                if ($arrRawQueryParamFiltersParamName === $rawQueryParamFiltersName) {
                    $params = $arrRawQueryParamFiltersValue;
                }

            }
            $returnString .= $rawQueryParamFiltersClosure($params);
        }
        return $returnString;
    }

    public function getRawQuery($rawQueryParams = array())
    {
        $this->getRawQueryParamFilters($rawQueryParams);

        $rawQuery = $this->getRawQueryString();

        return $rawQuery;
    }

    abstract function getRawQueryString();

    /**
     * @param $rawQueryParams
     * @throws \Exception
     */
    protected function getRawQueryParamFilters($rawQueryParams)
    {
        $campaignIdsFilter = $countryFilter = null;

        $filters = array();
        foreach ($rawQueryParams as $rawQueryParamName => $rawQueryParamArr) {
            if(!is_array($rawQueryParamArr)){
                $rawQueryParamArr = array($rawQueryParamArr);
            }
            foreach ($this->rawQueryParamFields as $rawQueryParamField) {
                if ($rawQueryParamField['field'] === $rawQueryParamName) {
                    if(empty($rawQueryParamField['type'])){
                        $rawQueryParamField['type'] = 'default';
                    }
                    if(empty($rawQueryParamField['queryOp'])){
                        $rawQueryParamField['queryOp'] = 'exclusive';
                    }
                    $filterCallback = null;
                    switch ($rawQueryParamField['type']) {
                        case 'multiselect':
                        case 'default':

                            // for now inclusive and exclusive are handled the same,
                            // I havent done any exclusive parameters that have multiple values like campaigns,
                            // it shouldn't be too hard to implement this


                            /*switch ($rawQueryParamField['queryOp']) {
                                case 'inclusive':
                                case 'exclusive':



                                    break;
                            }*/

                            $filterCallback = function () use ($rawQueryParamArr, $rawQueryParamField) {
                                return $this->getMultiFilterSql($rawQueryParamArr, $rawQueryParamField);
                            };
                            break;
                        case 'dateRange':
                            if (!empty($rawQueryParams['dateRange'])) {
                                $validator = Validator::make($rawQueryParams['dateRange'], [
                                    'start_date' => 'date',
                                    'end_date' => 'date'
                                ]);
                                if ($validator->fails()) {
                                    throw new \Exception($validator->messages());
                                }
                            } else {
                                $rawQueryParams['dateRange'] = array();
                            }

                            $dateParams = $rawQueryParams['dateRange'];
                            $that = $this;
                            $filterCallback = function ($dbField) use ($that, $dateParams) {
                                return $that->getDateRangeSql("AND", $dbField, $dateParams);
                            };
                            break;
                    }

                    $this->rawQueryParamFilters[$rawQueryParamName] = $filterCallback;
                    break;
                }
            }
        }
    }

    /**
     * @param $rawQueryParamArr
     * @param $rawQueryParamName
     * @param $rawQueryParamField
     * @return string
     */
    protected function getMultiFilterSql($rawQueryParamArr, $rawQueryParamField)
    {
        $firstRun = true;
        $strFilter = ' AND (';
        foreach ($rawQueryParamArr as $rawQueryParam) {
            if (empty($rawQueryParam)) {
                $strFilter = '';
            }
            if (!$firstRun) {
                $strFilter .= ' OR ';
            } else {
                $firstRun = false;
            }
            $strFilter .= ' (';
            $strFilter .= $rawQueryParamField['dbField'] . ' = ' . $this->setBoundParameter($rawQueryParam);
            $strFilter .= ' )';
        }
        $strFilter .= ' ) ';
        return $strFilter;
    }

}
