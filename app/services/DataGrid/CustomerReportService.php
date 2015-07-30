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
use Illuminate\Support\Facades\DB;
use App\Services\DataGrid\DataGridService;

class CustomerReportService extends  DataGridService
{
    protected $userService;
    protected $columnModel = array(
        array('label' => 'ID', 'width' => 100, 'table' => 'customers', 'id' => 'id', 'restricted' => true),
        array('label' => 'First Name', 'width' => 100, 'table' => 'customers', 'id' => 'FirstName', 'restricted' => true),
        array('label' => 'Last Name', 'width' => 100, 'table' => 'customers', 'id' => 'LastName', 'restricted' => true),
        array('label' => 'Email', 'width' => 100, 'table' => 'customers', 'id' => 'email', 'restricted' => true),
        array('label' => 'Phone', 'width' => 100, 'table' => 'customers', 'id' => 'Phone', 'restricted' => true),
        array('label' => 'Campaign', 'width' => 100, 'table' => 'campaigns', 'alias' => 'campaign_name', 'id' => 'name'),
        array('label' => 'Country', 'width' => 100, 'table' => 'country', 'alias' =>'country_name', 'id' => 'name'),
        array('label' => 'Registration Date', 'width' => 110, 'table' => 'customers', 'alias' => 'reg_date', 'id' => 'regTime', 'searchType' => 'daterange'),
        array('label' => 'Track Code A', 'width' => 100, 'table' => 'customers', 'id' => 'a_aid'),
        array('label' => 'Track Code B', 'width' => 100, 'table' => 'customers', 'id' => 'a_bid'),
        array('label' => 'Track Code C', 'width' => 100, 'table' => 'customers', 'id' => 'a_cid'),
        array('label' => 'Sale Status', 'width' => 100, 'table' => 'customers', 'id' => 'saleStatus', 'restricted' => true),
        array('label' => 'Deposit', 'width' => 100, 'table' => 'customer_balance', 'alias' => 'customer_lastBalance', 'id' => 'lastBalance', 'restricted' => true),
        array('label' => 'First Deposit Date', 'width' => 100, 'table' => 'customers', 'id' => 'firstDepositDate', 'searchType' => 'daterange')
    );


    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->url = route('customer-api');
        $this->query = Customer
            ::leftJoin('country', 'customers.Country', '=','country.id' )
            ->leftJoin('customer_balance', 'customers.id', '=','customer_balance.customerId' )
            ->leftJoin('campaigns', 'campaigns.id', '=','customers.campaignId' )
        ;

        $this->customNavButtons = array('exportCsv');
        parent::__construct($this->userService);
        $this->grid->setSortName('id');
        $this->grid->setSortOrder('DESC');
    }
}
