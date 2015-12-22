<?php
/**
 * @todo check for missing options
 *
 * Date: 10.12.14
 * Time: 16:35
 */
if (php_sapi_name() != 'cli') {
    die('Must run from command line');
}

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('log_errors', 0);
ini_set('html_errors', 0);

require __DIR__ . '/vendor/autoload.php';

$arguments = new \cli\Arguments(compact('strict'));
$arguments->addOption(
    'i',
    array(
        'default'     => false,
        'description' => 'Input'
    )
);
$arguments->addOption(
    'o',
    array(
        'default'     => false,
        'description' => 'Output'
    )
);

$arguments->addOption(
    'iban',
    array(
        'default'     => false,
        'description' => 'Sender IBAN number'
    )
);
$arguments->addOption(
    'bic',
    array(
        'default'     => false,
        'description' => 'BIC Code'
    )
);
$arguments->addOption(
    'name',
    array(
        'default'     => false,
        'description' => 'Sender Name'
    )
);

$arguments->addOption(
    'info',
    array(
        'default'     => false,
        'description' => 'Remittance information'
    )
);

$arguments->addOption(
    'format',
    array(
        'default'     => 'pain.001.001.03',
        'description' => 'PAIN format'
    )
);

$arguments->addFlag(array('help', 'h'), 'Show this help screen');

$arguments->parse();

if ( $arguments['help'] ) {
    echo $arguments->getHelpScreen();
    echo "\n\n";
}


if ( !file_exists( $arguments['i'] ) ) {
    \cli\err('Input file "%s" not found.', $arguments['i']);
    die();
}

$csv = fopen($arguments['i'] ,'r');

// Build group header
$group = new Digitick\Sepa\GroupHeader(
    md5( $arguments['info']),
    $arguments['name']
);

$count = 0;

$date = new DateTime();

$file = new Digitick\Sepa\TransferFile\CustomerCreditTransferFile( $group );

$paymentInfo = new Digitick\Sepa\PaymentInformation(
    md5( $arguments['info'] . $date->format( 'Y-m-d H:i:s' ) ),
    $arguments['iban'],
    $arguments['bic'],
    $arguments['name']
);

while(! feof($csv))
{
    $data = fgetcsv($csv);
    if ( ! is_array( $data ) ) {
        break;
    }

    // Allow no more than 60000
    if ( 60000 <= $count ) {
        break;
    }

    $bic = strtoupper( $data[2] );
    // Ignore invalid BIC codes
    if ( 1 !== preg_match( '/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic ) ) {
        \cli\line('Invalid BIC "%s" on line %d', $bic, $count + 1 );
        continue;
    }

    // Create transfer
    $transfer = new Digitick\Sepa\TransferInformation\CustomerCreditTransferInformation(
        floatval( $data[3]) , // SUM
        strtoupper( str_replace( ' ', '', $data[1] ) ), // IBAN
        iconv( 'utf-8','ascii//TRANSLIT', $data[0] ) // NAME
    );

    $transfer->setBic( $bic );

    $transfer->setRemittanceInformation( $arguments['info'] );


    $paymentInfo->addTransfer( $transfer );
    // Add export tag
    $count ++;
}

// If no payments where added return empty xml
if ( 0 === $count ) {
    return '';
}

$file->addPaymentInformation( $paymentInfo );

$builder = Digitick\Sepa\DomBuilder\DomBuilderFactory::createDomBuilder( $file, 'pain.001.001.03' );
$xml = $builder->asXml();

// Replace pain format
if ( 'pain.001.002.03' !== $arguments['format'] ) {
    $xml = str_replace(
        'pain.001.002.03',
        strip_tags( $arguments['format'] ),
        $xml
    );
}

file_put_contents( $arguments['o'], $xml );

\cli\line('XML written to "%s"', realpath($arguments['o']));