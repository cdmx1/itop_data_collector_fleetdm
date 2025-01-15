# FleetDM Host Data Collector for iTop

## Overview

This PHP-based collector integrates FleetDM with iTop, allowing users to synchronize host data from FleetDM into iTop. The collector retrieves host information from the FleetDM API and pushes the data to iTop’s synchronization mechanism.

## Prerequisites

- **iTop**: Make sure iTop is installed and accessible.
- **FleetDM API**: Ensure FleetDM is set up and hosts are enrolled in FleetDM.
- **PHP**: Installed and configured on the server.
- **Composer**: Installed to manage dependencies.

# Configuration

1. **Copy the example file**  
   Copy the example file into your `conf` folder as `params.local.xml`

2. **Update iTop connection settings**  
   Modify the values as follows:
   - `<itop_url>`: Set this to the actual URL of your iTop instance (e.g., `http://localhost`).
   - `<itop_login>`: Set this to the actual username of your iTop admin account (e.g., `admin`).
   - `<itop_password>`: Set this to the actual password of your iTop admin account (e.g., `xxxxxxxx`).

3. **Update FleetDM connection settings**  
   Modify the values as follows:
   - `<fleetdm_url>`: Set this to the actual URL of your FleetDM instance (e.g., `https://fxx.xxxx.xx`).
   - `<fleetdm_token>`: Set this to the actual token for your FleetDM instance (e.g., `xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`).

## Installation

1. **Download the Collector**

   Clone this repository on your desired collector server

   To Test the collector run below commands to install deps:
   sudo apt install php7.4 php7.4-xml 

   php ./exec.php

