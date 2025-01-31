# FleetDM to iTop CMDB Integration

## What is FleetDM?
FleetDM is a powerful open-source platform designed to provide real-time visibility into device inventory and operations across an organization's infrastructure. It leverages osquery, an operating system instrumentation tool, to collect detailed system information from devices such as laptops, desktops, and servers.

### Key Features of FleetDM
- **Host Management**: FleetDM collects and organizes system details (e.g., operating systems, software, hardware) from all enrolled devices.
- **Live Queries**: Run real-time queries on connected devices using SQL to gather information like running processes, disk encryption status, or installed packages.
- **Open Source**: FleetDM is free to use, fully transparent, and supported by a vibrant open-source community.

### How FleetDM Works
- **Osquery Integration**: FleetDM relies on osquery to interact with devices. Osquery acts as an agent installed on each endpoint.
- **Centralized Management**: The FleetDM server communicates with all enrolled devices to collect, store, and manage data in one centralized dashboard.
- **API Access**: FleetDM provides APIs to enable programmatic access to the collected data, making it easy to integrate with other tools like iTop.

## FleetDM to iTop Sync Extension
The FleetDM to iTop Sync Extension is a PHP-based collector that bridges FleetDM and iTop. It retrieves host data from FleetDM via its API and synchronizes the information with iTop's CMDB (Configuration Management Database). This integration provides a single pane of glass for asset management, improving operational efficiency and data visibility.

### Why Sync FleetDM with iTop?
By syncing FleetDM with iTop, organizations can:
- **Centralize Asset Data**: Consolidate all hardware and software details from FleetDM into iTop for better inventory management.
- **Enhance Visibility**: Use iTop as the central CMDB while retaining FleetDM’s real-time device management capabilities.
- **Streamline Operations**: Automate the transfer of host data, eliminating manual data entry or the need for two separate tools.
- **Support ITSM Processes**: Use the synced data to support IT Service Management (ITSM) activities like incident management, change management, and configuration control.
- **Better Alternative to OCS-Inventory**: Compared to FleetDM, OCS-Inventory is primarily focused on inventory management without real-time query capabilities.

## Prerequisites
Ensure the following requirements are met before proceeding:

### iTop Requirements:
- iTop installed and accessible.
- Administrator account credentials for iTop.

### FleetDM Setup:
- A configured FleetDM instance with enrolled hosts.
- API token for authentication.

### Server Environment:
- PHP (version 7.4 or later) installed.
- Git installed for repository management.

## Repository Setup

### Step 1: Clone the Repository
Open a terminal and clone the CDMX GitHub repository to your local system:

```bash
git clone https://github.com/cdmx1/itop_data_collector_fleetdm
```

Navigate to the cloned directory:

```bash
cd itop_data_collector_fleetdm
```

### Step 2: Install Dependencies
Run the following commands to install required dependencies:

```bash
sudo apt update
sudo apt install php7.4 php7.4-xml -y
```

## Configuration Steps

### Step 3: Configure iTop Connection
Copy the example configuration file:

```bash
cp conf/params.local.xml.example conf/params.local.xml
```

Open `params.local.xml` and update the iTop connection settings:

```xml
<itop_url>http://your-itop-instance</itop_url>
<itop_login>admin</itop_login>
<itop_password>your_password</itop_password>
```

### Step 4: Configure FleetDM Connection
Update the FleetDM settings in the same `params.local.xml` file:

```xml
<fleetdm_url>https://your-fleetdm-instance</fleetdm_url>
<fleetdm_token>your_fleetdm_api_token</fleetdm_token>
```

### Step 5: Configure Collectors for Different Device Types
For Laptops, Desktops, and Servers:
Locate the corresponding label for each type and update FleetDM IDs:

```xml
<fleet_dm_id type="laptop">laptop_label_id</fleet_dm_id>
<fleet_dm_id type="desktop">desktop_label_id</fleet_dm_id>
<fleet_dm_id type="server">server_label_id</fleet_dm_id>
```

Update the organization ID in the configuration:

```xml
<org_id>Your_Organization_ID</org_id>
```

## Running the Collector
Navigate to the repository directory:

```bash
cd itop_data_collector_fleetdm
```

Execute the collector script:

```bash
php exec.php
```

## Testing the Collector
After installation, verify the functionality by running:

```bash
php test.php
```

Ensure the connection to both FleetDM and iTop is successful without errors.
