<?php
/**
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use Psr\Log\NullLogger;

class Email_Parser_Unit_Test extends \Codeception\Test\Unit {

	protected function _before() {
		\WP_Mock::setUp();
	}

	protected function _tearDown() {
		parent::_tearDown();
		\WP_Mock::tearDown();
	}

	protected function test_parser( $filename, $patterns ) {

        global $project_root_dir;
        $email_file_path = $project_root_dir . '/tests/_data/' . $filename;

        $logger = new NullLogger();

        $email_parser = new Email_Parser( $patterns, $logger );

        $email_body = file_get_contents($email_file_path);

        $result = $email_parser->parse_email( $email_body );

        return $result;
    }



    public function get_directory( $subdir ) {

        global $project_root_dir;
        $emails_file_path = "{$project_root_dir}/tests/_data/emails/{$subdir}/";

        $files = glob("{$emails_file_path}/*.txt");

        assert( !empty( $files ), "No files found\n" );

        $filenames = array();
        foreach ($files as $filename) {

            $remove_dir = $project_root_dir  . '/tests/_data/';
            $filenames[] = array( str_replace( $remove_dir, '', $filename ) );
        }

        return $filenames;
    }



}
