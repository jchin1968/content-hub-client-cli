<?php

namespace AcquiaContentHubCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use AcquiaContentHubCli\Config\ClientConfig;
use Acquia\ContentHubClient\CDF\CDFObject;
use Symfony\Component\Yaml\Yaml;

class EntitiesListCommand extends Command
{
  protected function configure()
    {
        $this
            ->setName('entity:list')
            ->setDescription('List entities')
            ->addOption(
               'limit',
               'l',
               InputOption::VALUE_OPTIONAL,
               'Limit the number of results',
               1000
            )
            ->addOption(
               'start',
               's',
               InputOption::VALUE_OPTIONAL,
               'Offset to start from.',
               0
            )
            ->addOption(
               'type',
               't',
               InputOption::VALUE_OPTIONAL,
               'The type of entity to retrieve. Supported values include: rendered_entity, drupal8_content_entity, drupal8_config_entity, client.',
               ''
            )
            ->addOption(
                'origin',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Filter by the entity origin UUID.',
                ''
            )
            ->addOption(
                'filters',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filter by the entity attributes. Supported attributes include: entity_type, bundle',
                ''
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = ClientConfig::loadFromInput($input, $output);

        // Convert filter input string into an associative array.
        $filters = [];
        if (!empty($filter_input = $input->getOption('filters'))) {
          foreach (explode(',', $filter_input) as $filter) {
            $filters[strstr($filter, '=', true)] = trim(strstr($filter, '='), '=');
          }
        }

        $options = [
          'limit' => $input->getOption('limit'),
          'start' => $input->getOption('start'),
          'fields' => 'bundle,bundle_label,entity_type,entity_type_label,language,view_mode',
          'type' => $input->getOption('type'),
          'origin' => $input->getOption('origin'),
          'filters' => $filters,
        ];

        $client = $config->loadClient();
        $entities = $client->listEntities(array_filter($options));

        if (!is_array($entities['data'])) {
          $entities['data'] = [];
        }

        $rows = [];
        foreach ($entities['data'] as $cdf_data) {
          // if (isset($cdf_data['metadata']['data'])) {
          //   $cdf_data['metadata']['data'] = json_decode(base64_decode($cdf_data['metadata']['data']), true);
          // }

          $lang = $cdf_data['metadata']['languages'] ?? ['en'];
          $lang = reset($lang);
          $version = $cdf_data['metadata']['version'] ?? '';

          $entity_type = $cdf_data['attributes']['entity_type']['und'] ?? '';
          $entity_label = $cdf_data['attributes']['entity_type_label']['und'] ?? '';
          $bundle = $cdf_data['attributes']['bundle']['und'] ?? '';
          $bundle_label = $cdf_data['attributes']['bundle_label']['und'] ?? '';

          $rows[] = [
            'uuid' => $cdf_data['uuid'],
            'type' => $cdf_data['type'],
            'version' => $version,
            'entity_type' => $entity_type,
            'bundle' => $bundle,
            'origin' => $cdf_data['origin'],
          ];
        }

        if (empty($rows)) {
          $output->writeln('<error> No record found!</error>');
        } else {
            $table = new Table($output);
            $table
                ->setHeaders(array_keys(reset($rows)))
                ->setRows($rows)
            ;
            if (count($rows)) {
                $table->render();
            }

            $output->writeln('<info> Total records: ' . $entities['total'] . '</info>');
        }
    }

}
