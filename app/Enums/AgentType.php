<?php

namespace App\Enums;

enum AgentType: string
{
    case PM = 'pm';
    case BA = 'ba';
    case UX = 'ux';
    case ARCH = 'arch';
    case DEV = 'dev';
    case QA = 'qa';
    case DOC = 'doc';
    
    public function label(): string
    {
        return match($this) {
            self::PM => 'Project Manager',
            self::BA => 'Business Analyst',
            self::UX => 'UX Designer',
            self::ARCH => 'Architect',
            self::DEV => 'Developer',
            self::QA => 'Quality Assurance',
            self::DOC => 'Documentation',
        };
    }
    
    public function getPromptTemplate(): string
    {
        return storage_path("app/templates/{$this->value}.md");
    }
}