# Gevatter - GitHub Webhook Endpoint For Automatic Deployment

This is a fast and crudely put together script to refresh the code of my demos when a push to the corresponding repository has been made.

## Prerequisites

- webserver running PHP
  - `exec()` must be enabled and available
  - `hash_hmac()` must be enabled and available

## Getting started

- Copy `config-sample.json` to `config.json` and adjust
- Upload `config.json` and `deployment.php` to your webspace
  - Make sure the user executing your `deployment.php` is able to write to all required directories
- Setup your webhook [inside your projects settings](https://developer.github.com/webhooks/)
  - the `Content type` used is `application/json`

## Configuration

The `config-sample.json` contains everything you need to setup a `config.json` for a single webhook endpoint deploying multiple projects:

```json
{
  "debugMode": false,
  "deployments": {
    "someuser/sampleProject": {
      "name": "Sample Deployment",
      "secret": "ThisHasToBeChanged",
      "source": "dist/",
      "target": "/absolute/path/to/web/directory",
      "includeVersion": true,
      "versionFile": ["path/to/file.ext", "path/to/anotherfile.ext"],
      "versionString": "##VersionHash##",
      "customCommands": ["echo Hello", "echo World"]
    },
    "maybeanotheruser/someOtherProject": {
      "name": "Some Other Deployment",
      "secret": "ThisHasToBeChanged",
      "source": "",
      "target": "/absolute/path/to/web/directory",
      "includeVersion": false,
      "versionFile": "",
      "versionString": "",
      "customCommands": []
    }
  }
}
```

| Setting                      | Description                                                                                                   |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `debugMode`                  | Enables the debug mode of the script which will return the commands executed on the server                    |
| `deployments`                | List of your deployments, the key is always the full name (<username>/<repository>) of a repository in GitHub |
| `deployments.name`           | Custom name or description of your deployment, not used in code                                               |
| `deployments.secret`         | Secret string which will be used by GitHub and this script to validate the request                            |
| `deployments.source`         | Folder(s) inside your repository to take the data to files to deploy from (e.g. `dist/`)                      |
| `deployments.target`         | Absolute path on your webserver to deploy the files from the `source` folder to                               |
| `deployments.includeVersion` | Enables or disables the inclusion of a version hash in specifed files                                         |
| `deployments.versionFile`    | File in which the version hash will be put, can also be a list of files                                       |
| `deployments.versionString`  | String to search for in `versionFile` and replace with the current version hash                               |
| `deployments.customCommands` | Array of optional commands you can run during deployment                                                      |
