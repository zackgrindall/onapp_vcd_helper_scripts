# Helper scripts for OnApp vCD

## Configure billing plans automatically

By default, OnApp will create a new billing plan for each organization that is automatically imported. This script will move all users and organizations to use a centralized billing plan of your choice and add the relevant hypervisor, data store and network resources.

This script utilizes the OnApp API and should be run from the command line, using the php-cli package. If you are running OnApp internally just specify the internal IP address of your OnApp CP for the host argument. Please make sure that the API user has Administrator permissions.

```
wget https://raw.githubusercontent.com/zackgrindall/onapp_vcd_helper_scripts/master/configure_billing_plans.php
php configure_billing_plans.php host="http://demo.onapp.com" username="test" password="test"
```
