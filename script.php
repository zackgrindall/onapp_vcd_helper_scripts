<?php
/* vCD Billing Plan Configuration
 * by Zack Grindall <zack@onapp.com and Jim Freeman <jim@onapp.com>
 */

$args = array();
foreach ($argv as $a) {
  list($key, $val) = explode('=', $a);
  $args[$key] = $val;
}

class OnApp {

  public $host;
  public $username;
  public $password;

  function __construct($host, $username="admin", $password="changeme")
  {
      $this->host = $host;
      $this->username = $username;
      $this->password = $password;
  }

  /* API
   * GET
   */
  function get($method) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->host . "/" . $method . ".json");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output);
  }

  /* API
   * POST Add Resouces
   */
  function add_resources($plan_id, $content) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->host . "/billing/user/plans/".$plan_id."/resources.json");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-type: application/json"));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output);
  }

  /* Version
   * @get
   */
  function version() {
    return $this->get('version')->version;
  }

  /* User Groups
   * @get
   */
  function user_groups() {
    return $this->get('user_groups');
  }

  /* User Billing Plans
   * @get
   */
  function user_billing_plans() {
    return $this->get('/billing/user/plans');
  }

  /* VPC Hypervisor Zones
   * @get
   * server_type == vpc
   */
  function vpc_hypervisor_zones() {

    $hypervisor_zones = $this->get('settings/hypervisor_zones');
    $vpc_hypervisor_zones = array();

    foreach ($hypervisor_zones as $hypervisor_zone) {
      if ($hypervisor_zone->hypervisor_group->server_type == 'vpc') {
        $vpc_hypervisor_zones[] = (object) array('id' => $hypervisor_zone->hypervisor_group->id,'label' => $hypervisor_zone->hypervisor_group->label);
     }
    }

    return $vpc_hypervisor_zones;
  }

  /* VPC Data Store Zones
   * @get
   * provider_vdc_id != null
   */
  function vpc_data_store_zones() {

    $data_store_zones = $this->get('settings/data_store_zones');
    $vpc_data_store_zones = array();

    foreach ($data_store_zones as $data_store_zone) {
      if ($data_store_zone->data_store_group->provider_vdc_id != null) {
        $vpc_data_store_zones[] = (object) array('id' => $data_store_zone->data_store_group->id, 'label' => $data_store_zone->data_store_group->label);
      }
    }

    return $vpc_data_store_zones;
  }

  /* VPC Network Zones
   * @get
   * identifier !== routed-, isolated- or external-
   */
  function vpc_network_zones() {

    $network_zones = $this->get('settings/network_zones');
    $vpc_network_zones = array();

    foreach ($network_zones as $network_zone) {
      if (strpos($network_zone->network_group->identifier, 'routed-') !== false || strpos($network_zone->network_group->identifier, 'isolated-') !== false || strpos($network_zone->network_group->identifier, 'external-') !== false) {
        $vpc_network_zones[] = (object) array('id' => $network_zone->network_group->id, 'label' => $network_zone->network_group->label);
     }
    }

    return $vpc_network_zones;
  }

}

/* Declare OnApp class
 */
$onapp = new OnApp($args['host'], $args['username'], $args['password']);

print "Getting things ready for some vCD billing plan magic\n";

print "Checking API connection and obtaining the OnApp version number\n";
$onapp_version = $onapp->version();

if ($onapp_version != null) {
    print "[Success] You are running OnApp version " . $onapp_version. ".\n";
}else{
    print "[Error] OnApp version could not be obtained. Please check the API hostname and credentials supplied and try again. Exiting.\n";
    die;
}

print "Getting a list of billing user plans...\n";
$user_billing_plans = $onapp->user_billing_plans();

foreach ($user_billing_plans as $user_billing_plan) {
  print "[".$user_billing_plan->user_plan->id."] ".$user_billing_plan->user_plan->label."\n";
}

print "What billing user plan ID should be used? [1]: ";

$handle = fopen ("php://stdin","r");
$userPlanId = fgets($handle);

if (trim($userPlanId) == null) {
  $userPlanId = 1;
}elseif (!is_numeric(trim($userPlanId))) {
  print "That is a strange number. Exiting.\n";
  die;
}else{
  $userPlanId = trim($userPlanId);
}

print "Thank you, continuing...\n";

$user_groups = $onapp->user_groups();

foreach ($user_groups as $user_group) {
  print "[".$user_group->user_group->id."] ".$user_group->user_group->label." SET billing_plan_id=".$userPlanId."\n";
}

print "\nGetting VPC hypervisor zones and working some magic...\n";
$vpc_hypervisor_zones = $onapp->vpc_hypervisor_zones();

foreach ($vpc_hypervisor_zones as $hypervisor_zone) {
  print "> [".$hypervisor_zone->id."] ".$hypervisor_zone->label."\n";
  print "  POST /billing/user/plans/".$userPlanId."/resources.json\n";
  print '  {"resource_class":"Resource::HypervisorGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$hypervisor_zone->id.'"}'."\n";

  $add_resource = $onapp->add_resources($userPlanId, '{"resource_class":"Resource::HypervisorGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$hypervisor_zone->id.'"}');
  var_dump($add_resource);

  print "\n";
}

print "Getting VPC data store zones and working some magic...\n";
$vpc_data_store_zones = $onapp->vpc_data_store_zones();

foreach ($vpc_data_store_zones as $data_store_zone) {
    print "> [".$data_store_zone->id."] ".$data_store_zone->label."\n";
    print "  POST /billing/user/plans/".$userPlanId."/resources.json\n";
    print '  {"resource_class":"Resource::DataStoreGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$data_store_zone->id.'"}'."\n";

    $add_resource = $onapp->add_resources($userPlanId, '{"resource_class":"Resource::DataStoreGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$data_store_zone->id.'"}');
    var_dump($add_resource);

    print "\n";
}

print "Getting VPC network zones and working some magic...\n";
$vpc_network_zones = $onapp->vpc_network_zones();

foreach ($vpc_network_zones as $network_zone) {
    print "> [".$network_zone->id."] ".$network_zone->label."\n";
    print "  POST /billing/user/plans/".$userPlanId."/resources.json\n";
    print '  {"resource_class":"Resource::NetworkGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$network_zone->id.'"}'."\n";

    $add_resource = $onapp->add_resources($userPlanId, '{"resource_class":"Resource::NetworkGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$network_zone->id.'"}');
    var_dump($add_resource);

    print "\n";
}
