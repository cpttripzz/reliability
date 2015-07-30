<?php
/**
 * Created by PhpStorm.
 * User: zach
 * Date: 19/04/15
 * Time: 14:49
 */

namespace App\Services\DataGrid;

use App\Models\Customer;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use App\Services\DataGrid\DataGridService;
use Zend\Json\Expr;

class UserReportService extends RawDataReportService
{
    protected $userService;
    protected $rawQueryParamFields;
    protected $rawQueryParamFilters = array();

    public function __construct(UserService $userService)
    {
        $this->columnModel = array(
            array('label' => 'ID', 'width' => 100, 'table' => 'user', 'id' => 'id',
                 'queryParamField' => true),
            array('label' => 'First Name', 'width' => 100, 'table' => 'user', 'id' => 'first_name',
                'edittype' => 'text','editable' => true,'queryParamField' => true),
            array('label' => 'Last Name', 'width' => 100, 'table' => 'user', 'id' => 'last_name',
                'edittype' => 'text','editable' => true, 'queryParamField' => true),
            array('label' => 'Email', 'width' => 100, 'table' => 'user', 'id' => 'email',
                'edittype' => 'text','editable' => true, 'queryParamField' => true),
            array('label' => 'Company Name', 'width' => 100, 'table' => 'user', 'id' => 'company_name',
                'edittype' => 'text','editable' => true, 'queryParamField' => true),
            array('label' => 'Is Admin', 'width' => 100, 'table' => 'user', 'id' => 'is_admin',
                'editable' => true,'edittype' => 'checkbox','editvalues' => '1:0'
            ),
            array('label' => 'Campaigns', 'width' => 400, 'table' => 'campaign', 'id' => 'campaigns', 'alias' => 'campaigns',
                'editable' => true,'edittype' => 'multiselect','editvalues' => $userService->getCampaignIdsForSelectFilter()
            ),
            array('label' => 'Reports', 'width' => 400, 'table' => 'section', 'id' => 'sections', 'alias' => 'sections',
                'editable' => true,'edittype' => 'multiselect','editvalues' => $userService->getSections()
            ),
            array('label' => 'Fields', 'width' => 400, 'table' => 'scolumn', 'id' => 'scolumns', 'alias' => 'scolumns',
                'editable' => true,'edittype' => 'multiselect','editvalues' => $userService->getSColumns()
            ),
            array('label' => 'Api Key', 'width' => 300, 'table' => 'api_keys', 'id' => 'key',
                'edittype' => 'text','editable' => true, 'queryParamField' => true),
            array('label' => 'Allowed IP Range', 'width' => 300, 'table' => 'user', 'id' => 'allowed_ip_range',
                'edittype' => 'text','editable' => true),

        );


        $this->url = route('user-api');
        $this->connectionName = 'ott';

        $this->customNavButtons = array('deleteRow','exportCsv', 'changePassword', 'createApiKey');
        parent::__construct($userService);


        $this->grid->setSortName('id')
            ->setMultiselect(true)
            ->setSortOrder('DESC')
            ->setInlineNavOptions([
                'addParams' => [
                    'position'=>"last",
                    'restoreAfterError' => false,
                    'addRowParams' => [
                        'successfunc' => new Expr('
                            function(response){
                                if(response.responseJSON.success){
                                    toastr.success("User updated");
                                    jQuery("'.$this->grid->getGridIdentifier().'").trigger("reloadGrid");
                                } else {
                                    jQuery.each(jQuery.parseJSON(response.responseJSON.message), function( index, value ) {
                                        toastr.error(value);
                                    });
                                    return false;
                                }
                            }
                        ')
                    ]

                ],
                'editParams' => [
                    'restoreAfterError' => false,
                    'successfunc' => new Expr('
                        function(response){
                            if(response.responseJSON.success){
                                toastr.success("User updated");
                                jQuery("'.$this->grid->getGridIdentifier().'").trigger("reloadGrid");
                            } else {
                                jQuery.each(jQuery.parseJSON(response.responseJSON.message), function( index, value ) {
                                    toastr.error(value);
                                });
                                return false;
                            }
                        }
                    ')
                ]
            ])
            ->setEditUrl($this->url)
           ;
    }

    function getRawQueryString()
    {
        $rawQuery = <<<SQL
SELECT user.id, user.first_name,user.last_name,user.email,
  user.is_admin, user.company_name,api_keys.key,user.allowed_ip_range,
  GROUP_CONCAT(DISTINCT campaign.id) as campaigns,
  GROUP_CONCAT(DISTINCT scolumn.id) as scolumns,
  GROUP_CONCAT(DISTINCT section.id) as sections
FROM user
  LEFT JOIN `user_campaign` ON `user_campaign`.`user_id` = `user`.`id`
  LEFT JOIN `campaign` ON `campaign`.`id` = `user_campaign`.`campaign_id`
  LEFT JOIN `user_scolumn` ON `user_scolumn`.`user_id` = `user`.`id`
  LEFT JOIN `scolumn` ON `scolumn`.`id` = `user_scolumn`.`scolumn_id`
  LEFT JOIN `user_section` ON `user_section`.`user_id` = `user`.`id`
  LEFT JOIN `section` ON `section`.`id` = `user_section`.`section_id`
  LEFT JOIN `api_keys` ON `api_keys`.`user_id` = `user`.`id`
  WHERE 1
  {$this->renderRawQueryParamFilters()}
GROUP BY user.id

SQL;
        return $rawQuery;
    }
}

