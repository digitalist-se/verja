<?php

namespace Verja\Command;

use lygav\slackbot\SlackBot;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Console\Input\InputOption;
use GuzzleHttp\Client;
use Exception;
use ZipArchive;
use Symfony\Component\Yaml\Yaml;
use Badoo\Jira\Issue\CreateRequest;
use Badoo\Jira\REST\Client as JiraClient;

class GetCVECommand extends Command
{

    protected $container;
    protected static $defaultName = 'getCVE';

    public function __construct(ContainerBuilder $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $HelpText = '<info>Display the about</info>';

        $this->setName("getCVE")
            ->setDescription("get CVE")
            ->setDefinition(
                [
                    new InputOption(
                        'url',
                        'u',
                        InputOption::VALUE_OPTIONAL,
                        'URL to download CVE json from',
                        'https://nvd.nist.gov/feeds/json/cve/1.1/nvdcve-1.1-recent.json.zip'
                    ),
                    new InputOption(
                        'to',
                        't',
                        InputOption::VALUE_OPTIONAL,
                        'Path to save file',
                        '/tmp/nvdcve-1.1-recent.json.zip'
                    ),
                    new InputOption(
                        'extract-to',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'Path to extract to',
                        '/tmp/cve/'
                    ),
                    new InputOption(
                        'slack-url',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'Slack URL',
                        null
                    ),
                    new InputOption(
                        'cpe-path',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'Path to text file that contains what to watch for.',
                        'cpe.txt'
                    ),
                    new InputOption(
                        'config-file',
                        'f',
                        InputOption::VALUE_OPTIONAL,
                        'Path to config file',
                        'config.yml'
                    ),

                ]
            )
            ->setHelp($HelpText);
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getOption('url');
        $to_file = $input->getOption('to');
        $extract_to = $input->getOption('extract-to');
        $slack_url = $input->getOption('slack-url');
        $cpe_path = $input->getOption('cpe-path');
        $this->getCVE($url, $to_file);
        $unzip_file = $this->unzip($to_file, $extract_to);
        $path = "$extract_to$unzip_file";
        $stack = file($cpe_path, FILE_IGNORE_NEW_LINES);
        $search = $this->readJSON($path, $stack);
        $jira = false;
        $slack = false;

        $config_file = $input->getOption('config-file');
        //$running_path = getcwd();
        $config = Yaml::parseFile("$config_file");


        if ($search === null) {
            echo "Happy days, no CVE founds today!";
           // exit;
        } else {
            foreach ($search as $note) {
                    $id = $note['id'];
                    $description = $note['description'];
                    $references = $note['references'];
                    $cpe = $note['cpe'];
                    $emoji = $note['emoji'];
                    $vendor = $note['vendor'];
                    $message = "$emoji *$id* \n$description\nReference: $references";
                if (isset($config['slack']['enabled'])) {
                    $slack = $config['slack']['enabled'];
                }
                if ($slack === true) {
                    $slack_endpoint = $config['slack']['endpoint'];
                    $slack_channel = $config['slack']['channel'];
                    $slack_emoji = $config['slack']['emoji'];
                    $slack_user_name = $config['slack']['username'];
                    if (!isset($slack_url)) {
                        $slack_url =  $slack_endpoint;
                    }
                    $slack_url =  $slack_endpoint;
                    $slack_options = [
                        'username' => $slack_user_name,
                        'icon_emoji' => $slack_emoji,
                        'channel' => $slack_channel,
                        'as_user' => false,
                    ];
                    $slack = new SlackBot($slack_url, $slack_options);
                    $slack->text($message);
                    $slack->send();
                }

                if (isset($config['jira']['enabled'])) {
                    $jira = $config['jira']['enabled'];
                }

                if ($jira === true) {
                    $jira_url = $config['jira']['url'];
                    $jira_user = $config['jira']['user'];
                    $jira_secret = $config['jira']['secret'];
                    $jira_project_key = $config['jira']['project_key'];
                    $jira_issue_type = $config['jira']['issue_type'];
                    $jira_description_field = $config['jira']['description_field'];
                    $jira_tag_field = $config['jira']['tag_field'];
                    $jira_summary_prefix = $config['jira']['summary_prefix'];
                    $jira_tags = $config['jira']['tags'];
                    $id = $note['id'];
                    $vendor = $note['vendor'];

                    $jira = JiraClient::instance();
                    $jira
                        ->setJiraUrl($jira_url)
                        ->setAuth($jira_user, $jira_secret);

                    $request = new CreateRequest("$jira_project_key", "$jira_issue_type");
                    $request
                        ->setSummary("$jira_summary_prefix $id $vendor")
                        ->setFieldValue("$jira_description_field", "$description Reference: $references")
                        ->setFieldValue("$jira_tag_field", array_values($jira_tags));

                    $issue = $request->send();
                }
            }
        }
    }

    protected function getCVE($url, $to_file)
    {
        try {
            $client = new Client();
            $client->get(
                $url,
                [
                    'save_to' => $to_file,
                ]
            );
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    protected function unzip($file, $extract_to)
    {
        try {
            $zip = new ZipArchive;
            $open = $zip->open($file);
            $zip->extractTo($extract_to);
            $name = $zip->getNameIndex(0);
            $zip->close();
            return $name;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    protected function readJSON($file, $stack)
    {
        $content = file_get_contents($file);
        $json = json_decode($content, true);
        $items = $json['CVE_Items'];
        $matches = null;

        foreach ($stack as $vendor) {
            foreach ($items as $key => $item) {
                if (isset($item['configurations']['nodes'][0]['cpe_match'][0]['cpe23Uri'])) {
                    $cpe = $item['configurations']['nodes'][0]['cpe_match'][0]['cpe23Uri'];
                    if (stripos($cpe, $vendor) !== false) {
                        $id = $item['cve']['CVE_data_meta']['ID'];
                        $description = $item['cve']['description']['description_data'][0]['value'];
                        $references = $item['cve']['references']['reference_data'][0]['url'];
                        $emoji = $vendor;
                        if (strpos($emoji, ':') !== false) {
                            $emoji = substr($emoji, strpos($emoji, ":") + 1);
                        }

                        $matches[$key] = [
                            'id' => $id,
                            'description' => $description,
                            'references' => $references,
                            'cpe' => $cpe,
                            'emoji' => ":$emoji:",
                            'vendor' => $vendor,
                        ];
                    }
                }
            }
        }
        return $matches;
    }
}
