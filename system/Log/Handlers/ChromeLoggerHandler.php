<?php namespace CodeIgniter\Log\Handlers;

use CodeIgniter\Hooks\Hooks;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Class ChromeLoggerHandler
 *
 * Allows for logging items to the Chrome console for debugging.
 * Requires the ChromeLogger extension installed in your browser.
 *
 * @see https://craig.is/writing/chrome-logger
 *
 * @package CodeIgniter\Log\Handlers
 */
class ChromeLoggerHandler extends BaseHandler implements HandlerInterface
{
	/**
	 * Version of this library - for ChromeLogger use.
	 *
	 * @var float
	 */
	const VERSION = 1.0;

	/**
	 * The number of strack frames returned from the backtrace.
	 *
	 * @var int
	 */
	protected $backtraceLevel = 0;

	/**
	 * The final data that is sent to the browser.
	 *
	 * @var array
	 */
	protected $json = [
		'version' => self::VERSION,
		'columns' => ['log', 'backtrace', 'type'],
		'rows'    => [],
	];

	/**
	 * The header used to pass the data.
	 *
	 * @var string
	 */
	protected $header = 'X-ChromeLogger-Data';

	/**
	 * Maps the log levels to the ChromeLogger types.
	 *
	 * @var array
	 */
	protected $levels = [
		'emergency' => 'error',
		'alert'     => 'error',
		'critical'  => 'error',
		'error'     => 'error',
		'warning'   => 'warn',
		'notice'    => 'warn',
		'info'      => 'info',
		'debug'     => 'info',
	];

	//--------------------------------------------------------------------

	public function __construct(array $config = [])
	{
		parent::__construct($config);

		global $request;

		$this->json['request_uri'] = (string)$request->uri;

		Hooks::on('post_controller', [$this, 'sendLogs'], HOOKS_PRIORITY_HIGH);
	}

	//--------------------------------------------------------------------

	/**
	 * Handles logging the message.
	 * If the handler returns false, then execution of handlers
	 * will stop. Any handlers that have not run, yet, will not
	 * be run.
	 *
	 * @param $level
	 * @param $message
	 *
	 * @return bool
	 */
	public function handle($level, $message): bool
	{
		// Format our message
		$message = $this->format($message);

		// Generate Backtrace info
		$backtrace = debug_backtrace(false, $this->backtraceLevel);
		$backtrace = end($backtrace);

		$backtraceMessage = 'unknown';
		if (isset($backtrace['file']) && isset($backtrace['line']))
		{
			$backtraceMessage = $backtrace['file'].':'.$backtrace['line'];
		}

		// Default to 'log' type.
		$type = '';

		if (in_array($level, $this->levels))
		{
			$type = $this->levels[$level];
		}

		$this->json['rows'][] = [$message, $backtraceMessage, $type];

		return true;
	}

	//--------------------------------------------------------------------

	/**
	 * Converts the object to display nicely in the Chrome Logger UI.
	 *
	 * @param $object
	 */
	protected function format($object)
	{
		if (! is_object($object))
		{
			return $object;
		}

		// @todo Modify formatting of objects once we can view them in browser.
		$objectArray = (array)$object;

		$objectArray['___class_name'] = get_class($object);

		return $objectArray;
	}

	//--------------------------------------------------------------------

	/**
	 * Attaches the header and the content to the passed in request object.
	 */
	public function sendLogs(ResponseInterface &$response=null)
	{
		if (is_null($response))
		{
			global $response;
		}

		$data = base64_encode(utf8_encode(json_encode($this->json)));

	    $response->setHeader($this->header, $data);
	}

	//--------------------------------------------------------------------


}