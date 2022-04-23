<?php

/**
 * The main admin page for Nexcess MAPPS customers.
 *
 * @global \Nexcess\MAPPS\Settings $settings The current settings object.
 */

use Nexcess\MAPPS\Support\Branding;

// Fetch the company name.
$company_name = Branding::getCompanyName();

?>
<div class="mapps-wrap sbapp-background">
	<div id="storebuilderapp-react" data-js="storebuilderapp-react">
		<?php /* ReactApp: Setup and Wizards */ ?>
	</div>
</div>
