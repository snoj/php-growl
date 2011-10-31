<?PHP
    class Growl
    {
        const GROWL_PRIORITY_LOW = -2;
        const GROWL_PRIORITY_MODERATE = -1;
        const GROWL_PRIORITY_NORMAL = 0;
        const GROWL_PRIORITY_HIGH = 1;
        const GROWL_PRIORITY_EMERGENCY = 2;

        private $appName;
        public $address;
        private $notifications;
        private $password;
        private $port;

        public function __construct($address, $password = '', $app_name = 'PHP Growl')
        {
            $this->appName       = utf8_encode($app_name);
            $this->address       = $address;
            $this->notifications = array();
            $this->password      = $password;
            $this->port          = 9887;
			
			if(@inet_pton($this->address) === false && ($tmp = dns_get_record($this->address, DNS_ANY)) !== false) {
				$record_a = null;
				$record_aaaa = null;
				
				foreach($tmp as $v) {
					if($record_a === null && $v['type'] == 'A') {
						$record_a = $v['ip'];
					}
					
					if(defined('AF_INET6') && $record_aaaa === null && $v['type'] == 'AAAA') {
						$record_aaaa = $v['ipv6'];
					}
				}
				
				if($record_aaaa !== null) {
					$this->address = $record_aaaa;
				}
				if($record_a !== null) {
					$this->address = $record_a;
				}
			}
        }

        public function addNotification($name, $enabled = true)
        {
            $this->notifications[] = array('name' => utf8_encode($name), 'enabled' => $enabled);
        }

        public function register()
        {
            $data         = '';
            $defaults     = '';
            $num_defaults = 0;

            for($i = 0; $i < count($this->notifications); $i++)
            {
                $data .= pack('n', strlen($this->notifications[$i]['name'])) . $this->notifications[$i]['name'];
                if($this->notifications[$i]['enabled'])
                {
                    $defaults .= pack('c', $i);
                    $num_defaults++;
                }
            }

            // pack(Protocol version, type, app name, number of notifications to register)
            $data  = pack('c2nc2', 1, 0, strlen($this->appName), count($this->notifications), $num_defaults) . $this->appName . $data . $defaults;
            $data .= pack('H32', md5($data . $this->password));

            return $this->send($data);
        }

        public function notify($name, $title, $message, $priority = 0, $sticky = false)
        {
            $name     = utf8_encode($name);
            $title    = utf8_encode($title);
            $message  = utf8_encode($message);
            $priority = intval($priority);

            $flags = ($priority & 7) * 2;
            if($priority < 0) $flags |= 8;
            if($sticky) $flags |= 256;

            // pack(protocol version, type, priority/sticky flags, notification name length, title length, message length. app name length)
            $data = pack('c2n5', 1, 1, $flags, strlen($name), strlen($title), strlen($message), strlen($this->appName));
            $data .= $name . $title . $message . $this->appName;
            $data .= pack('H32', md5($data . $this->password));

            return $this->send($data);
        }

        private function send($data)
        {
            if(function_exists('socket_create') && function_exists('socket_sendto'))
            {
				if(strlen(inet_pton($this->address)) > 4 && defined('AF_INET6')) {
					$sck = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
				} elseif(strlen(inet_pton($this->address)) == 4) {
					$sck = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
				} else {
					$error_str = "Error creating Socket";
					
					if(strlen(inet_pton($this->address)) > 4 && !defined('AF_INET6')) {
						$error_str = sprintf("IPv6 address used, but IPv6 not enabled with this php build.", $this->address);
					}
					
					throw new Exception($error_str);
				}
                socket_sendto($sck, $data, strlen($data), MSG_WAITALL, $this->address, $this->port);
                return true;
            }
            elseif(function_exists('fsockopen'))
            {
                $fp = fsockopen('udp://' . $this->address, $this->port);
                fwrite($fp, $data);
                fclose($fp);
                return true;
            }

            return false;
        }
    }
