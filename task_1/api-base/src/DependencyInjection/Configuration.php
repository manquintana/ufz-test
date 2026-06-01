<?php
namespace Ufz\ApiBase\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('api_base');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->arrayNode('aws_s3')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('disable_content_sha256_in_presign')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
