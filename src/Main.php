<?php
/**
 * The Main class file.
 *
 * @author     outcompute
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    0.0.1
 * @since      File available since Release 0.0.1
 */

namespace OutCompute\PHPWatchDog;

class Main {
    /**
     * Although the class is implemented as a singleton, the instance is not held in a property.
     * Instead a flag is kept, and any further attempts to re-init raise an exception.
     * @var boolean
     * @access private
     */
    private static $_isConfigured = false;

    /**
     * Maintains two keys, files & functions, which are populated from the access policy supplied
     * to the constructor. Is used to check if a trapped call should be allowed to proceed or not.
     * @var array
     * @access private
     */
    private $_watchlist;

    /**
     * Set from the second argument to the constructor, specifies if execution should halt on incidence.
     * If true, execution will halt by calling die(), else will continue.
     * @var boolean
     * @access private
     */
    private $_haltOnIncident;

    /**
     * A list of PHP function calls which are watched to monitor file access, or any attempt to change
     * the execution environment.
     * @var boolean
     * @access private
     */
    private $_phpInternalOpsToWatch;

    private function __construct($watchlist, $haltOnIncident) {
        # Set the flag so that subsequent calls can be stopped
        self::$_isConfigured = true;

        $this->_watchlist = array(
                                    'files' => array(),
                                    'functions' => array()
        );
        # _haltOnIncident is used in _shutdownAccess().
        $this->_haltOnIncident = $haltOnIncident;

        if(
            array_key_exists('files', $watchlist) &&
            is_array($watchlist['files'])
        ) {
            foreach($watchlist['files'] as $file => $filters) {
                # We can't use an entry if a filter doesn't contain the 'default' key
                if(
                    !is_array($filters) ||
                    !array_key_exists('default', $filters)
                )
                    continue;

                $this->_watchlist['files'][$file] = array(
                    'default' => $filters['default'],
                    'except' => array()
                );
                if(
                    array_key_exists('except', $filters) &&
                    is_array($filters['except'])
                ) {
                    foreach($filters['except'] as $k => $filter) {
                        # Every except block entry has to be an array
                        # with a maximum of two keys, file & scope.
                        # If both keys are present, they are &-ed, and the results
                        # are applied only if both of them match. To see how this is
                        # applied, check out the method _getSuitableAction().
                        if(
                            is_array($filter) &&
                            count($filter) > 0
                        ) {
                            if(isset($filter['scope'])) $filter['scope'] = $this->_normalizeCallKey($filter['scope']);
                            if($filters['default'] == 'block') {
                                # If the default action is to block, then that means we are allowing in this except combination,
                                # and if we have a specific file from where access is being allowed, then we need to
                                # make sure that nothing is trying to insert code into this file and escape detection.
                                # So add it to the monitor list of files.
                                if(array_key_exists('file', $filter)) {
                                    $this->_watchlist['files'][$filter['file']] = array(
                                        'default' => 'block'
                                    );
                                }
                            }
                            # Add this entry to the watchlist
                            $this->_watchlist['files'][$file]['except'][] = $filter;
                        }
                    }
                }
            }
        }
        # Prohibit accessing this very file as well in execution
        $this->_watchlist['files'][__FILE__] = array(
            'default' => 'block'
        );

        # These are the functions which can change a file's access mode, or contents.
        # All calls to these methods will be trapped, and their arguments inspected to see
        # if any watched file attribute or content is being modified.
        # These functions will be monitored in addition to the user-requested ones.
        #   If you add a function here, make sure to edit _isAccessingWatchedFile and add the
        # function to the switch block to inform in which argument of the function you add is the
        # file path available.
        $this->_phpInternalOpsToWatch = array(
            'ini_set()', 'chmod()', 'chown()', 'copy()', 'exec()', 'file_put_contents()', 'fopen()', 'link()',
            'move_uploaded_file()', 'popen()', 'rename()', 'symlink()', 'touch()', 'unlink()'
        );
        foreach($this->_phpInternalOpsToWatch as $function) {
            $function = $this->_normalizeCallKey($function);
            # We'll trap every call to one of these functions to check for the file in question
            aop_add_before($function, array($this, 'process'));
        }

        if(
            array_key_exists('functions', $watchlist) &&
            is_array($watchlist['functions'])
        ) {
            foreach($watchlist['functions'] as $function => $filters) {
                # We can't use an entry if a filter doesn't contain the 'default' key
                if(
                    !is_array($filters) ||
                    !array_key_exists('default', $filters)
                )
                    continue;
                $function = $this->_normalizeCallKey($function);
                if($function != NULL) {
                    $this->_watchlist['functions'][$function] = array(
                        'default' => $filters['default'],
                        'except' => array()
                    );
                    if(
                        array_key_exists('except', $filters) &&
                        is_array($filters['except'])
                    ) {
                        foreach($filters['except'] as $k => $filter) {
                            # Every except block entry has to be an array
                            # with a maximum of two keys, file & scope.
                            # If both keys are present, they are &-ed, and the results
                            # are applied only if both of them match. To see how this is
                            # applied, check out the method _getSuitableAction().
                            if(
                                is_array($filter) &&
                                count($filter) > 0
                            ) {
                                if(isset($filter['scope'])) $filter['scope'] = $this->_normalizeCallKey($filter['scope']);
                                if($filters['default'] == 'block') {
                                    # If the default action is to block, then that means we are allowing in this except combination,
                                    # and if we have a specific file from where access is being allowed, then we need to
                                    # make sure that nothing is trying to insert code into this file and escape detection.
                                    # So add it to the monitor list of files.
                                    if(array_key_exists('file', $filter)) {
                                        $this->_watchlist['files'][$filter['file']] = array(
                                            'default' => 'block'
                                        );
                                    }
                                }
                                # Add this entry to the watchlist
                                $this->_watchlist['functions'][$function]['except'][] = $filter;
                            }
                        }
                    }
                    aop_add_before($function, array($this, 'process'));
                }
            }
        }

        # And don't allow anyone to call aop_add_before and aop_add_after
        $this->_watchlist['functions']['aop_add_after()'] = array(
            'default' => 'block'
        );
        aop_add_before("aop_add_after()", array($this, 'process'));
        $this->_watchlist['functions']['aop_add_before()'] = array(
            'default' => 'block'
        );
        aop_add_before("aop_add_before()", array($this, 'process'));
    }

    /**
    * @param array $watchlist           An array of the following format, specifying the access policies
    *                                   array(
    *                                       'functions' => array(
    *                                           'targetFunction()' => array(
    *                                               'default' => 'block', # Mandatory Key, can be either 'allow' or 'block'
    *                                               # If default is block, the below combinations in 'except' will allow targetFunction().
    *                                               # If default is allow, then the below combinations will block targetFunction().
    *                                               'except' => array(
    *                                                   array(
    *                                                       'file' => 'AllowedFile.php',
    *                                                       'scope' => 'AllowedClass->AllowedMethod()'
    *                                                   ),
    *                                                   ... # You can specify more combinations here targeting targetFunction()
    *                                               )
    *                                           )
    *                                       ),
    *                                       'files' => array(
    *                                           'logs.log' => array(
    *                                               'default' => 'allow',
    *                                               'except' => array(
    *                                                   array(
    *                                                       'file' => 'upload.php' # Any access to this file (read/write) will be blocked
    *                                                   ),
    *                                                   ...
    *                                               )
    *                                           )
    *                                       )
    *                                   )
    * @param boolean $haltOnIncident    Specifies if the library should call die() after throwing the Exception() anytime a policy violation incident happens
    *
    * @param array      $watchlist the string to quote
    * @param boolean    $haltOnIncident an integer of how many problems happened.
    *
    * @return null
    *
    * @access private
    */
    public static function configure($watchlist = array(), $haltOnIncident = true) {
        # If configure is being called a second time, then do not let it go through
        if(self::$_isConfigured != false) {
            throw new \Exception(__CLASS__." : Attempt to redefine watchlist.");
            die();
        }

        # Call the constructor
        new Main($watchlist, $haltOnIncident);
    }

    public function process($AJP) {
        $argumentsUsed = $AJP->getArguments(); # This will get us the arguments supplied to the trapped operation

        # We need to parse the backtrace to find out from what scope and file the trapped operation was generated
        $backtrace = debug_backtrace();
        if(!isset($backtrace[count($backtrace)])) {
            $backtrace[] = array(
                                    'function' => '',
                                    'type' => '',
                                    'class' => 'global scope ', # This is a dirty hack used to signify the global scope. Please help me.
                                    'file' => $backtrace[1]['file']
            );
        }
        for($i = count($backtrace) - 1; $i > 0; $i--) {
            # Set the 'class' key
            $backtrace[$i]['class'] = array_key_exists('class', $backtrace[$i]) ? $backtrace[$i]['class'] : '';

            # Set the 'type' key
            $backtrace[$i]['type'] = array_key_exists('type', $backtrace[$i]) ? $backtrace[$i]['type'] : '';

            # Set the 'scope' key at the one lesser index($i)
            $backtrace[$i-1]['scope'] = $this->_normalizeCallKey($backtrace[$i]['class'].$backtrace[$i]['type'].$backtrace[$i]['function']);
        }
        $trappedOperation = $this->_parseTrace($backtrace[1]);
        $trappedOperation['arguments'] = $argumentsUsed;

        $file = $this->_isAccessingWatchedFile($trappedOperation);
        if($file != NULL) {
            $fileAccessSpecifiers = $this->_watchlist['files'][$file['file']];

            if($this->_getSuitableAction($fileAccessSpecifiers, $trappedOperation) == 'block')
                $this->_shutdownAccess(array('type' => 'file', 'file' => $file['file']), $trappedOperation);
        }

        $function = $this->_isAccessingWatchedFunction($trappedOperation);
        if($function != NULL) {
            $functionAccessSpecifiers = $this->_watchlist['functions'][$function['function']];

            if($this->_getSuitableAction($functionAccessSpecifiers, $trappedOperation) == 'block')
                $this->_shutdownAccess(array('type' => 'function'), $trappedOperation);
        }

        # Special case watch to trap attempts to disable AOP with the ini_set
        if(
            $trappedOperation['function'] == 'ini_set()' &&
            strtolower(trim($trappedOperation['arguments'][0])) == 'aop.enable' &&
            ($trappedOperation['arguments'][1] == 1 || $trappedOperation['arguments'][1] == false)
        )
            $this->_shutdownAccess(array('type' => 'function'), $trappedOperation);
    }

    /**
     * Normalize all method names to the format:
     *  namespace\class\method()
     */
    private function _normalizeCallKey($key) {
        $key = trim(ltrim($key, '\\'));
        if($key == '') return NULL;
        else {
            if(substr($key, -2) != "()") $key .= "()";
            return $key;
        }
    }

    private function _parseTrace($entry) {
        if(!isset($entry['class'])) $entry['class'] = '';
        if(!isset($entry['type'])) $entry['type'] = '';
        return array(
                        'file' => $entry['file'],
                        'scope' => $entry['scope'],
                        'function' => $this->_normalizeCallKey($entry['class'].$entry['type'].$entry['function'])
        );
    }

    /**
     * Used to detect if two files are same.
     * If a shorter relative file path is found within a longer absolute file path,
     * treat them as similar, and return true.
     */
    private function _areTheseFilesSame($fileA, $fileB) {
        $fileA = trim($fileA);
        $fileB = trim($fileB);
        if(strlen($fileA) > strlen($fileB))
            $matched = strpos($fileA, $fileB);
        else
            $matched = strpos($fileB, $fileA);
        return $matched === false ? false : true;
    }

    /**
     * Used to detect if two scopes are same.
     * If a shorter relative scope is found within a longer absolute scope,
     * treat them as similar, and return true.
     */
    private function _areTheseScopesSame($scopeA, $scopeB) {
        $scopeA = trim($scopeA);
        $scopeB = trim($scopeB);
        if(strlen($scopeA) > strlen($scopeB)) {
            # $scopeA is longer, so check for the possibility that $scopeA may contain $scopeB ...
            $matched = strpos($scopeA, $scopeB);
        } else {
            # ... and vice versa
            $matched = strpos($scopeB, $scopeA);
        }
        return $matched === false ? false : true;
    }

    /**
     * Used to detect if a file that we are supposed to watch is being attempted to write or modify
     * $OP holds the details of the current execution
     * $ARGS holds the arguments to the current function call
     */
    private function _isAccessingWatchedFile($trappedOperation) {
        $functionInQuestion = NULL; # Name of the resolved normalized PHP function being used

        # Iterate through all known file-modifying functions to find the one being used in this case
        foreach($this->_phpInternalOpsToWatch as $suspect) {
            if($this->_areTheseScopesSame($suspect, $trappedOperation['function'])) {
                $functionInQuestion = $suspect;
                break;
            }
        }

        $targetFiles = NULL;
        # Next, we try to get the names of the files being supplied in the arguments
        # $ARGS is the array of arguments being supplied, and the index in it is
        # decided from the PHP documentation for the function
        switch($functionInQuestion) {
            case 'chmod()': # bool chmod ( string $filename , int $mode )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            case 'chown()': # bool chown ( string $filename , mixed $user )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            case 'copy()': # bool copy ( string $source , string $dest [, resource $context ] )
                $targetFiles[] = $trappedOperation['arguments'][0];
                $targetFiles[] = $trappedOperation['arguments'][1];
            break;
            case 'exec()': # string exec ( string $command [, array &$output [, int &$return_var ]] )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            case 'file_put_contents()': # int file_put_contents ( string $filename , mixed $data [, int $flags = 0 [, resource $context ]] )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            case 'fopen()': # resource fopen ( string $filename , string $mode [, bool $use_include_path = false [, resource $context ]] )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            case 'link()': # bool link ( string $target , string $link )
                $targetFiles[] = $trappedOperation['arguments'][0];
                $targetFiles[] = $trappedOperation['arguments'][1];
            break;
            case 'move_uploaded_file()': # bool move_uploaded_file ( string $filename , string $destination )
                $targetFiles[] = $trappedOperation['arguments'][0];
                $targetFiles[] = $trappedOperation['arguments'][1];
            break;
            case 'popen()': # resource popen ( string $command , string $mode )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            case 'rename()': # bool rename ( string $oldname , string $newname [, resource $context ] )
                $targetFiles[] = $trappedOperation['arguments'][0];
                $targetFiles[] = $trappedOperation['arguments'][1];
            break;
            case 'symlink()': # bool symlink ( string $target , string $link )
                $targetFiles[] = $trappedOperation['arguments'][0];
                $targetFiles[] = $trappedOperation['arguments'][1];
            break;
            case 'touch()': # bool touch ( string $filename [, int $time = time() [, int $atime ]] )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            case 'unlink()': # bool unlink ( string $filename [, resource $context ] )
                $targetFiles[] = $trappedOperation['arguments'][0];
            break;
            default:
                return NULL;
            break;
        }

        if($targetFiles != NULL) {
            $watchedFiles = array_keys($this->_watchlist['files']); # Get the list of files we are supposed to be watching
            foreach($watchedFiles as $suspect) { # For every file we are supposed to watch ...
                foreach($targetFiles as $oneFile) { # ... and a file we have as argument in this current call ...
                    if($this->_areTheseFilesSame($suspect, $oneFile)) { # ... we have a suspect operation if they match ...
                        return array(
                                    'file' => $suspect # ... so return with the name of the file to which an attempt to modify was mad
                        );
                    }
                }
            }
        }
        return NULL;
    }

    /**
     * Used to detect if a function that is not supposed to be called is being called
     * $OP holds the details of the current execution
     */
    private function _isAccessingWatchedFunction($trappedOperation) {
        $watchedFunctions = array_keys($this->_watchlist['functions']);
        foreach($watchedFunctions as $suspect) { # For every function call we are supposed to watch ...
            if($this->_areTheseScopesSame($suspect, $trappedOperation['function'])) { # ... we have a suspect operation if the current function call matches ...
                return array(
                            'function' => $suspect # ... so return with the name of the function
                );
            }
        }
    }

    /**
     *
     */
    private function _getSuitableAction($accessSpecifiers, $trappedOperation) {
        $defaultAction = $accessSpecifiers['default'];

        # If we don't have 'except' block, return default action
        if(
            !array_key_exists('except', $accessSpecifiers) ||
            !is_array($accessSpecifiers['except']) ||
            count($accessSpecifiers['except']) == 0
        )
            return $defaultAction;

        foreach($accessSpecifiers['except'] as $specifier) {
            # There can be multiple specifiers for a file/function, so iterate through all of them
            # with the first match of scope and file(from which programmatic access is allowed/blocked) taking precedence
            if(array_key_exists('scope', $specifier))
                $scopesAreSame = $this->_areTheseScopesSame($specifier['scope'], $trappedOperation['scope']);
            else
                $scopesAreSame = true; # Default to true if no scope is targeted, meaning match all scopes
            if(array_key_exists('file', $specifier))
                $filesAreSame = $this->_areTheseFilesSame($specifier['file'], $trappedOperation['file']);
            else
                $filesAreSame = true; # Default to true if no file is mentioned, meaning match access from any file

            if($scopesAreSame && $filesAreSame)
                return $defaultAction == 'allow' ? 'block' : 'allow';
        }

        return $defaultAction; # If we had a match, and no combination matched, block
    }

    /**
     * Used to raise an exception and halt execution if a violation has been detected.
     */
    private function _shutdownAccess($violationInfo, $trappedOperation) {
        switch($violationInfo['type']) {
            case 'file':
                throw new \Exception(__CLASS__." : {$violationInfo['file']} was being written to by {$trappedOperation['function']}, called in {$trappedOperation['scope']} in {$trappedOperation['file']}");
                break;
            case 'function':
                throw new \Exception(__CLASS__." : {$trappedOperation['function']} was being called by {$trappedOperation['scope']} in {$trappedOperation['file']}");
                break;
        }
        if($this->_haltOnIncident == true)
            die();
    }
}
?>
