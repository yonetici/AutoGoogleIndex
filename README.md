# Auto Google Indexing

Auto Google Indexing is a powerful WordPress plugin that automates the process of notifying Google about new and updated content on your website. Save time and improve indexing efficiency by leveraging Google's Indexing API seamlessly within WordPress.

## Features
- **Automated Indexing**: Automatically notify Google when posts or custom post types like "questions" are published or updated.
- **Service Account Integration**: Easily upload your Google API service account JSON file directly through the plugin's settings.
- **Comprehensive Logs**: View detailed logs of all indexing requests with results and timestamps.
- **Admin-Friendly UI**: Simple and intuitive settings page for managing API integration and monitoring logs.

## How It Works
1. Upload your Google API service account JSON file via the plugin settings.
2. When new posts or "question" custom post types are published, the plugin automatically sends a request to the Google Indexing API.
3. Check the logs page for detailed information on each indexing request.

## Installation
1. Download the plugin as a ZIP file from this repository.
2. Go to your WordPress dashboard, navigate to **Plugins > Add New**, and upload the ZIP file.
3. Activate the plugin.
4. Go to **Settings > Auto Google Indexing** to configure your API credentials.

## Usage
- Upload your `service-account.json` file in the settings page.
- Once configured, the plugin works automatically for:
  - **Published posts**
  - **Custom post types like "questions"**

## Logs
You can view a complete log of all indexing requests by navigating to **Indexing Logs** in your WordPress admin menu.

## Requirements
- WordPress 6.0 or later
- PHP 7.4 or later
- A valid Google Indexing API service account

## Contribute
Feel free to contribute by submitting pull requests or reporting issues.

## License
This project is licensed under the MIT License.
