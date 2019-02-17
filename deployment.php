<?php
  ini_set("display_errors", true);
  /**
   * Workaround for getallheaders() not present in PHP-FPM
   * @see https://stackoverflow.com/a/41427998 
   */
  if (!function_exists('getallheaders')) {
      function getallheaders() {
      $aHeaders = [];
      foreach ($_SERVER as $sName => $sValue) {
          if (substr($sName, 0, 5) == 'HTTP_') {
              $aHeaders[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($sName, 5)))))] = $sValue;
          }
      }
      return $aHeaders;
      }
  }

  /**
   * Small helper to set the wanted HTTP-Statuscode, returnmessage and
   * exitstate for PHP. The exitstate is not really necessary, however I like
   * being in control.
   */ 
  function ReturnAndExit($iHTTPStatus = 200, $aReturnArray = [], $iExitState = 1) {
    http_response_code($iHTTPStatus);
    echo json_encode($aReturnArray);
    exit($iExitState);
  }

  /**
   * Verifies the request to ensure it has been issued by GitHub
   * @see http://isometriks.com/verify-github-webhooks-with-php
   */ 
  function CheckValidityOfRequest($aHeaders, $oPayload, $sSecret) {
    if (empty($aHeaders) || empty($oPayload)) {
      return false;
    }

    $sSignature = $aHeaders['X-Hub-Signature'];

    // Split signature into algorithm and hash
    list($sAlgorithm, $sHash) = explode('=', $sSignature, 2);
     
    // Calculate hash based on payload and the secret
    $payloadHash = hash_hmac($sAlgorithm, $oPayload, $sSecret);

    if ($sHash === $payloadHash) {
      return true;
    }

    return false;
  }

  // Check if config has been setup and load it.
  if (!file_exists("config.json")) {
    ReturnAndExit(
      999,
      ["error" => "Please set up your configuration."],
      1
    );
  }
  $oConfigObject = json_decode(file_get_contents('config.json'), true);

  // Get payload and request headers
  $aRequestHeaders = getallheaders();
  $oRequestPayload = file_get_contents('php://input');
  $oPayloadObject = json_decode($oRequestPayload, true);

  // Check if request contains payload data
  if (empty($oPayloadObject)) {
    ReturnAndExit(
      401,
      ["error" => "I'm sorry, Dave, I'm afraid I can't do that."],
      1
    );
  }

  // prepare some shortcuts
  $sRepositoryName = $oPayloadObject['repository']['full_name'];
  $sRepositoryLink = $oPayloadObject['repository']['clone_url'];

  
  $oRepositoryConfig = $oConfigObject['deployments'][$sRepositoryName];

  // Check if request has been made for a configured repository
  if (empty($oRepositoryConfig)) {
    ReturnAndExit(
      401,
      ["error" => "Repository not found in configuration."],
      1
    );
  }

  /**
   * Change this to any random string which is also placed inside GitHubs
   * Webhook form.
   */
  define('SECRET', $oRepositoryConfig['secret']);
  /**
   * Be careful that only the contents of your repositories src-folder reside 
   * here as this directory is cleared everytime the script is running.
   */
  define('SOURCEFOLDER', $oRepositoryConfig['source']);
  define('TARGETFOLDER', $oRepositoryConfig['target']);
  /** 
   * This has to be true if you want to include the most recent commit hash 
   * inside some file of your project
   */
  define('INCLUDEVERSION', $oRepositoryConfig['includeVersion']);
  /**
   * VERSIONFILE has to be some file containing the VERSIONSTRING relative to
   * your repository tree.
   * VERSIONHASH will be searched and replaced with your latest commit hash.
   *
   * VERSIONFILE can also be a list of files to be processed:
   * define('VERSIONFILE', ['path/to/file.ext', 'path/to/anotherFile.ext']);
   */
  define('VERSIONFILE', $oRepositoryConfig['versionFile']);
  define('VERSIONSTRING', $oRepositoryConfig['versionString']);
  define('GITLINK', $sRepositoryLink);
  /**
   * Enables the debugging mode in which the run commands and corresponding 
   * return messages (if any) will be output after running the script.
   * ATTENTION
   * This is not recommended for production use as it may publish critical
   * information concerning your webserver setup.
   */ 
  define('DEBUG_MODE', $oConfigObject['debugMode']);

  /**
   * The first three arrays contain the needed shell commands to make the
   * automatic deployment happen.
   * I splitted the commands to make the logic for any custom commands 
   * (like replacing some string in some file with the most recent commit hash)
   * more useable.
   * 
   * rm -rf * - Removes every file inside TARGETFOLDER recursively und forcefully
   * git clone <repository> tmp - Should be familiar...
   * 
   * mv tmp/src/* . - Move every file from your repositories src-folder 
   *                  to the root directory
   * rm -rf tmp - Remove tmp folder
   */ 
  $aInitialCommands = [
    'rm -rf *',
    'git clone ' . GITLINK . ' tmp',
  ];
  $aCustomCommands = $oRepositoryConfig['customCommands'];
  $aFinalCommands = [
    'mv tmp/' . SOURCEFOLDER . '* .',
    'rm -rf tmp'
  ];
  $aReturnMessages = [];

  /**
   * If INCLUDEVERSION is enabled, the most recent commit hash is saved in a
   * variable via 'git rev-parse HEAD' and used to replace VERSIONSTRING inside
   * the defined VERSIONFILE via using sed.
   */
  if (INCLUDEVERSION) {
    if (!is_array(VERSIONFILE)) {
      $aCustomCommands[] = 'cd tmp && version="$(git rev-parse HEAD)" && sed -i "s/' . VERSIONSTRING . '/$version/g" "' . VERSIONFILE . '"';
    } else {
      foreach (VERSIONFILE as $key => $versionfile) {
        $aCustomCommands[] = 'cd tmp && version="$(git rev-parse HEAD)" && sed -i "s/' . VERSIONSTRING . '/$version/g" "' . $versionfile . '"';
      }
    }
  };

  /**
   * Checking if the request was really issued by GitHub with your defined
   * secret and for some needed functions 
   */
  if (!function_exists('hash_hmac')) {
    ReturnAndExit(
      500,
      ["error" => "hash_hmac() not enabled on this server."],
      1
    );
  }

  /** 
   * HTTP 999: User is unwilling to comply to simple tasks.
   * @see https://youtu.be/xoMgnJDXd3k
   * Seriously, change it.
   */
  if (SECRET == 'ThisHasToBeChanged') {
    ReturnAndExit(
      999,
      ["error" => "User of deployment script was unable to follow directions."],
      1
    );
  }

  if (
    !CheckValidityOfRequest($aRequestHeaders, $oRequestPayload, SECRET)
  ) {
    ReturnAndExit(
      401,
      ["error" => "I'm sorry, Dave, I'm afraid I can't do that."],
      1
    );
  }

  if (!function_exists('exec')) {
    ReturnAndExit(
      500,
      ["error" => "exec() not enabled on this server."],
      1
    );
  }

  // Put everything together. 
  $aCommands = array_merge($aInitialCommands, $aCustomCommands, $aFinalCommands);

  try {
    /**
     * Go to the directory your application is residing.
     * Firstly, I put the 'cd'-command inside the regular commands not knowing,
     * that all exec-calls are free of any context and thus resulting in the
     * deletion of the first version of this file.
     */
    chdir(TARGETFOLDER);
    foreach ($aCommands as $sCommand) {
      $aReturnMessages[] = exec($sCommand);
    };

    if(DEBUG_MODE) {
      ReturnAndExit(
        200,
        json_encode(array_combine($aCommands, $aReturnMessages)),
        1
      );
    }
  } catch (Exception $oException) {
    ReturnAndExit(
      500,
      ['error' => $oException->getMessage()],
      1
    );
  }
?>