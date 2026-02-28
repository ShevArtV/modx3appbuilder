<?php

namespace ComponentBuilder;

enum ElementType: string
{
    case CHUNK = 'modChunk';
    case SNIPPET = 'modSnippet';
    case PLUGIN = 'modPlugin';
    case TEMPLATE = 'modTemplate';
    case TV = 'modTemplateVar';
    case SETTING = 'modSystemSetting';
    case MENU = 'modMenu';
    case EVENT = 'modEvent';
    case POLICY = 'modAccessPolicy';
    case POLICY_TEMPLATE = 'modAccessPolicyTemplate';

    public function getPluralName(): string
    {
        return match($this) {
            self::CHUNK => 'chunks',
            self::SNIPPET => 'snippets',
            self::PLUGIN => 'plugins',
            self::TEMPLATE => 'templates',
            self::TV => 'tvs',
            self::SETTING => 'settings',
            self::MENU => 'menus',
            self::EVENT => 'events',
            self::POLICY => 'policies',
            self::POLICY_TEMPLATE => 'policyTemplates',
        };
    }
}
