<?php
/* Configure vCD Billing Plans
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
    $output = json_decode($output);
    if (isset($output->errors)) {
      print "[Error] ".$output->errors->type[0];
    }
  }

  function delete($method) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->host . "/" . $method . ".json");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-type: application/json"));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    $output = curl_exec($ch);
    curl_close($ch);
    $output = json_decode($output);
    if (isset($output->errors)) {
      print "[Error] ".$output->errors->base[0];
    }
  }

  function put($method, $content) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->host . "/" . $method . ".json");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-type: application/json"));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    $output = curl_exec($ch);
    curl_close($ch);
    $output = json_decode($output);
    if (isset($output->errors)) {
      print "[Error] ".$output->errors->type[0];
    }
  }

  function version() {
    return $this->get('version')->version;
  }

  function user_groups() {
    return $this->get('user_groups');
  }

  function update_user_groups_billing_plan($userPlanId) {
    $user_groups = $this->get('user_groups');
    foreach ($user_groups as $user_group) {
      $this->put('user_groups/'.$user_group->user_group->id, '{"user_group":{"billing_plan_id":"'.$userPlanId.'"}');
    }
  }

  function update_user_billing_plan($userPlanId) {
    $users = $this->get('users');
    foreach ($users as $user) {
      if ($user->user->id != 2) {
        $this->put('users/'.$user->user->id, '{"user":{"billing_plan_id":"'.$userPlanId.'"}');
      }
    }
  }

  function user_billing_plans() {
    return $this->get('/billing/user/plans');
  }

  function company_billing_plans() {
    return $this->get('/billing/company/plans');
  }

  function remove_user_billing_plan($id) {
    return $this->delete('/billing/user/plans/'.$id);
  }

  function remove_company_billing_plan($id) {
    return $this->delete('/billing/company/plans/'.$id);
  }

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

print "Obtaining user groups...\n";

$user_groups = $onapp->get('user_groups');
foreach ($user_groups as $user_group) {
  print "[".$user_group->user_group->id."] ".$user_group->user_group->label."\n";
}

print "Update all user groups with billing plan id ".$userPlanId."? [yn]: ";

$handle = fopen ("php://stdin","r");
$billingGroupUserPlans = fgets($handle);

if (trim($billingGroupUserPlans) == 'y') {
  $onapp->update_user_groups_billing_plan($userPlanId);
}elseif (trim($billingGroupUserPlans) == 'n') {
  print "Skipping step...\n";
}else{
  print "Invalid option. Exiting.\n";
  die;
}

print "Obtaining users...\n";

$users = $onapp->get('users');
foreach ($users as $user) {
  print "[".$user->user->id."] ".$user->user->email."\n";
}

print "Update all users with billing plan id ".$userPlanId."? [yn]: ";

$handle = fopen ("php://stdin","r");
$billingGroupUserPlans = fgets($handle);

if (trim($billingGroupUserPlans) == 'y') {
  $onapp->update_user_billing_plan($userPlanId);
}elseif (trim($billingGroupUserPlans) == 'n') {
  print "Skipping step...\n";
}else{
  print "Invalid option. Exiting.\n";
  die;
}

print "Obtaining user and company billing plans...\n";
print "User billing plans:\n";

$user_billing_plans = $onapp->user_billing_plans();

foreach ($user_billing_plans as $user_billing_plan) {
  if ($user_billing_plan->user_plan->id != 1 || $user_billing_plan->user_plan->id != $userPlanId) {
    print "[".$user_billing_plan->user_plan->id."] ".$user_billing_plan->user_plan->label." *\n";
  }else{
    print "[".$user_billing_plan->user_plan->id."] ".$user_billing_plan->user_plan->label."\n";
  }
}

print "Company billing plans:\n";

$company_billing_plans = $onapp->company_billing_plans();

foreach ($company_billing_plans as $company_billing_plan) {
  if ($company_billing_plan->company_plan->id != 2) {
    print "[".$company_billing_plan->company_plan->id."] ".$company_billing_plan->company_plan->label." *\n";
  }else{
    print "[".$company_billing_plan->company_plan->id."] ".$company_billing_plan->company_plan->label."\n";
  }
}

print "Remove all billing plans marked *? [yn]: ";

$handle = fopen ("php://stdin","r");
$billingPlanCleanup = fgets($handle);

if (trim($billingPlanCleanup) == 'y') {

  print "Cleaning up billing plans...\n";

  foreach ($user_billing_plans as $user_billing_plan) {
    if ($user_billing_plan->user_plan->id != 1 || $user_billing_plan->user_plan->id != $userPlanId) {
      print "DELETE [".$user_billing_plan->user_plan->id."] ".$user_billing_plan->user_plan->label."\n";
      $onapp->remove_user_billing_plan($user_billing_plan->user_plan->id);
    }
  }

  foreach ($company_billing_plans as $company_billing_plan) {
    if ($company_billing_plan->company_plan->id != 2) {
      print "DELETE [".$company_billing_plan->company_plan->id."] ".$company_billing_plan->company_plan->label."\n";
      $onapp->remove_company_billing_plan($company_billing_plan->company_plan->id);
    }
  }

}elseif (trim($billingPlanCleanup) == 'n') {
  print "Skipping step...\n";
}else{
  print "Invalid option. Exiting.\n";
  die;
}

print "Adding VPC hypervisor zone resources...\n";
$vpc_hypervisor_zones = $onapp->vpc_hypervisor_zones();

foreach ($vpc_hypervisor_zones as $hypervisor_zone) {
  print "[".$hypervisor_zone->id."] ".$hypervisor_zone->label."\n";
  $onapp->add_resources($userPlanId, '{"resource_class":"Resource::HypervisorGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$hypervisor_zone->id.'"}');
  print "\n";
}

print "Adding VPC data store zone resources...\n";
$vpc_data_store_zones = $onapp->vpc_data_store_zones();

foreach ($vpc_data_store_zones as $data_store_zone) {
    print "[".$data_store_zone->id."] ".$data_store_zone->label."\n";
    $onapp->add_resources($userPlanId, '{"resource_class":"Resource::DataStoreGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$data_store_zone->id.'"}');
    print "\n";
}

print "Adding VPC network zone resources...\n";
$vpc_network_zones = $onapp->vpc_network_zones();

foreach ($vpc_network_zones as $network_zone) {
    print "[".$network_zone->id."] ".$network_zone->label."\n";
    $onapp->add_resources($userPlanId, '{"resource_class":"Resource::NetworkGroup","in_master_zone":"1","target_type":"Pack","target_id":"'.$network_zone->id.'"}');
    print "\n";
}
