<?php

namespace alexeevdv\React\Smpp;

use alexeevdv\React\Smpp\Pdu\BindReceiver;
use alexeevdv\React\Smpp\Pdu\BindTransceiver;
use alexeevdv\React\Smpp\Pdu\CancelSm;
use alexeevdv\React\Smpp\Pdu\Contract\BindTransmitter;
use alexeevdv\React\Smpp\Pdu\Contract\Pdu;
use alexeevdv\React\Smpp\Pdu\Contract\SubmitSm;
use alexeevdv\React\Smpp\Pdu\DeliverSmResp;
use alexeevdv\React\Smpp\Pdu\EnquireLink;
use alexeevdv\React\Smpp\Pdu\Factory;
use alexeevdv\React\Smpp\Pdu\QuerySm;
use alexeevdv\React\Smpp\Pdu\ReplaceSm;
use alexeevdv\React\Smpp\Pdu\Unbind;
use Evenement\EventEmitter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Server as SocketServer;
use React\Socket\ServerInterface;

final class Server extends EventEmitter implements ServerInterface
{
    /**
     * @var SocketServer
     */
    private $server;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct($uri, LoopInterface $loop, array $context = array(), $logger = null)
    {
        $server       = new SocketServer($uri, $loop, $context);
        $this->server = $server;
        $this->logger = $logger ?? new Logger('react-smpp', [new StreamHandler('php://stderr')]);

        $that = $this;
        $this->server->on('connection', function (ConnectionInterface $conn) use ($loop, $that) {
            $connection = new Connection($conn);

            // TODO start timer for enquire_link

            $connection->on('data', function ($data) use ($connection) {
                $pduFactory = new Factory;
                try {
                    echo(chunk_split(bin2hex($data), 2, " ")).PHP_EOL;
                    $pdu = $pduFactory->createFromBuffer($data);
                    $connection->emit('pdu', [$pdu]);
                } catch (\Exception $e) {
                    // TODO GENERIC_NACK
                    $connection->emit('error', [$e]);
                    $this->logger->error('error', ['message'=>chunk_split($e->getMessage(), 2, " ")]);
                }
            });

            $connection->on('pdu', function (Pdu $pdu) use ($connection) {
                if ($pdu instanceof BindReceiver) {
                    $this->logger->debug('< BindReceiver', [
                        'sequence'     => $pdu->getSequenceNumber(),
                        'getSystemId'  => $pdu->getSystemId(),
                        'getPassword'  => $pdu->getPassword(),
                        'getCommandId' => $pdu->getCommandId(),
                        'getBody'      => chunk_split(bin2hex($pdu->__toString()), 2, " "),
                    ]);
                    return $connection->emit('bind_receiver', [$pdu]);
                }

                if ($pdu instanceof BindTransmitter) {
                    $this->logger->debug('< BindTransmitter', [
                        'sequence'     => $pdu->getSequenceNumber(),
                        'size (bytes)' => $pdu->getCommandLength(),
                        'getSystemId'  => $pdu->getSystemId(),
                        'getPassword'  => $pdu->getPassword(),
                        'getCommandId' => $pdu->getCommandId(),
                        'getBody'      => chunk_split(bin2hex($pdu->__toString()), 2, " "),
                    ]);
                    return $connection->emit('bind_transmitter', [$pdu]);
                }

                if ($pdu instanceof QuerySm) {
                    return $connection->emit('query_sm', [$pdu]);
                }

                if ($pdu instanceof SubmitSm) {
                    $this->logger->debug('< SubmitSm', [
                        'sequence'     => $pdu->getSequenceNumber(),
                        'getCommandId' => $pdu->getCommandId(),
                        'getBody'      => chunk_split(bin2hex($pdu->__toString()), 2, " "),
                    ]);
                    return $connection->emit('submit_sm', [$pdu]);
                }

                if ($pdu instanceof DeliverSmResp) {
                    return $connection->emit('deliver_sm_resp', [$pdu]);
                }

                if ($pdu instanceof Unbind) {
                    $this->logger->debug('< Unbind', [
                        'sequence'     => $pdu->getSequenceNumber(),
                        'getCommandId' => $pdu->getCommandId(),
                        'getBody'      => chunk_split(bin2hex($pdu->__toString()), 2, " "),
                    ]);
                    return $connection->emit('unbind', [$pdu]);
                }

                if ($pdu instanceof ReplaceSm) {
                    return $connection->emit('replace_sm', [$pdu]);
                }

                if ($pdu instanceof CancelSm) {
                    return $connection->emit('cancel_sm', [$pdu]);
                }

                if ($pdu instanceof BindTransceiver) {
                    $this->logger->debug('< BindTransceiver', [
                        'sequence'     => $pdu->getSequenceNumber(),
                        'getSystemId'  => $pdu->getSystemId(),
                        'getPassword'  => $pdu->getPassword(),
                        'getCommandId' => $pdu->getCommandId(),
                        'getBody'      => chunk_split(bin2hex($pdu->__toString()), 2, " "),
                    ]);
                    return $connection->emit('bind_transceiver', [$pdu]);
                }

                if ($pdu instanceof EnquireLink) {
                    $this->logger->debug('< EnquireLink', [
                        'sequence'     => $pdu->getSequenceNumber(),
                        'getCommandId' => $pdu->getCommandId(),
                        'getBody'      => chunk_split(bin2hex($pdu->__toString()), 2, " "),
                    ]);
                    return $connection->emit('enquire_link', [$pdu]);
                }
            });

            $connection->on('send', function (Pdu $pdu) use ($connection) {
                $this->logger->debug('Send >', [
                    'sequence'     => $pdu->getSequenceNumber(),
                    'getCommandId' => $pdu->getCommandId(),
                    'getBody'      => chunk_split(bin2hex($pdu->__toString()), 2, " "),
                ]);
                $connection->write($pdu->__toString());
            });

            $that->emit('connection', [$connection]);
        });
    }

    public function getAddress()
    {
        return $this->server->getAddress();
    }

    public function pause()
    {
        $this->server->pause();
    }

    public function resume()
    {
        $this->server->resume();
    }

    public function close()
    {
        $this->server->close();
    }
}
