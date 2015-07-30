<?php
/**
 * Created by PhpStorm.
 * User: zach
 * Date: 19/04/15
 * Time: 14:49
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

class CountryReportService extends RawDataReportService
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->columnModel = array(
            array('label' => 'Country', 'width' => 200, 'table' => 'country', 'alias' => 'country', 'id' => 'name', 'defaultValue' => null, 'searchType' => 'select',
                'svalues' => $userService->getCountryListForSelectFilter()
            ),
            array('label' => 'Leads', 'width' => 200, 'table' => 'clients', 'type' => 'raw', 'alias' => 'leads', 'rawValue' => 'COUNT(clients.id)', 'id' => 'lead'),
            array('label' => 'Customers', 'width' => 200, 'table' => 'clients', 'type' => 'raw', 'alias' => 'customers', 'rawValue' => 'COUNT(clients.id) - SUM(clients.islead)','id' => 'customers'),
            array('label' => 'Deposits Count', 'width' => 200, 'table' => 'deposits', 'type' => 'raw', 'alias' => 'dcount', 'rawValue' => 'COALESCE(deposits.dcount, 0)','id' => 'dcount'),
            array('label' => 'Deposits Amount', 'width' => 200, 'table' => 'deposits', 'type' => 'raw', 'alias' => 'damount', 'rawValue' => 'COALESCE(deposits.damount, 0)','id' => 'damount'),
            array('label' => 'FTD', 'width' => 200, 'table' => 'deposits', 'type' => 'raw', 'alias' => 'FTD', 'rawValue' => 'COALESCE(deposits.FTD, 0)','id' => 'FTD'),
        );

        $this->rawQueryParamFields = array(
            array('field' => 'campaigns', 'type' => 'multiselect', 'sort' => false, 'queryOp' => 'inclusive', 'dbField' => 'customers.campaignId',),
            array('field' => 'country', 'type' => 'multiselect', 'sort' => true, 'queryOp' => 'inclusive', 'dbField' => 'customers.Country'),
            array('field' => 'dateRange', 'type' => 'dateRange', 'defaultValue' => Json::encode(array('start' => date(Config::get('app.dateformat')), 'end' => date(Config::get('app.dateformat')))), 'sort' => false)
        );

        $this->url = route('country-api');
        $this->connectionName = 'so_rep';
        $this->sortname = 'damount';
        $this->postDataFields = array(
            'campaigns' => new Expr("jQuery('#'+'%field%').val();"),
            'dateRange' => new Expr("JSON.stringify($('#'+'%field%').daterangepicker('getRange'));")
        );
        $this->customNavButtons = array('exportCsv');

        parent::__construct($userService);
    }

   function getRawQueryString()
    {
        $rawQuery = <<<SQL

 SELECT
    country.name AS country,
    COUNT(clients.id) leads,
    COUNT(clients.id) - SUM(clients.islead) AS customers,
    COALESCE(deposits.dcount, 0) AS dcount,
    COALESCE(deposits.damount, 0) AS damount,
    COALESCE(deposits.FTD, 0) AS FTD
FROM
    OneTwoTrade_platform.country
        RIGHT JOIN
    (SELECT
        IF((customers.campaignId >= 408
                AND customers.campaignId <> 417)
                OR customers.campaignId IN (322 , 323, 324), (SELECT
                    name
                FROM
                    campaigns
                WHERE
                    id = customers.campaignId), SUBSTRING_INDEX(sub_campaigns.param, '_', 2)) AS aff_id,
            customers.Country AS country,
            customers.id AS id,
            customers.isLead AS islead
    FROM
        OneTwoTrade_platform.customers, OneTwoTrade_platform.sub_campaigns
    WHERE
        customers.isDemo = 0
            AND sub_campaigns.id = customers.subCampaignId
            AND ((customers.campaignId >= 408
            AND customers.campaignId <> 417)
            OR customers.campaignId IN (322 , 323, 324, 339, 340))
            {$this->renderRawQueryParamFilters(array('campaigns' ,'dateRange' => "customers.regTime"))}

            ) AS clients ON country.id = clients.country
        LEFT JOIN
    (SELECT
        customers.Country AS country,
            IF((customers.campaignId >= 408
                AND customers.campaignId <> 417)
                OR customers.campaignId IN (322 , 323, 324), (SELECT
                    name
                FROM
                    campaigns
                WHERE
                    id = customers.campaignId), SUBSTRING_INDEX(sub_campaigns.param, '_', 2)) AS aff_id,
            SUM(IF(customer_deposits.id > 0, 1, 0)) AS dcount,
            SUM(customer_deposits.amountUSD) AS damount,
            SUM(IF(customers.firstDepositDate = customer_deposits.confirmTime, 1, 0)) AS FTD,
            SUM(IF(customers.firstDepositDate = customer_deposits.confirmTime, customer_deposits.amountUSD, 0)) AS FTDAmount
    FROM
        OneTwoTrade_platform.customers
    LEFT JOIN OneTwoTrade_platform.customer_deposits ON (OneTwoTrade_platform.customers.id = OneTwoTrade_platform.customer_deposits.customerId)
    LEFT JOIN OneTwoTrade_platform.sub_campaigns ON (OneTwoTrade_platform.sub_campaigns.id = OneTwoTrade_platform.customers.subCampaignId)
    WHERE
        customer_deposits.paymentMethod != 'Bonus'
            AND customers.isDemo = 0
            AND customer_deposits.status = 'approved'
            {$this->renderRawQueryParamFilters(array('campaigns','dateRange' => "customer_deposits.confirmTime"))}
    GROUP BY customers.Country) AS deposits ON country.id = deposits.country
GROUP BY Country

SQL;
        return $rawQuery;
    }
}
