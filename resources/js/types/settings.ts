export interface BotSettings {
    id: number;
    system_prompt: string;
    welcome_message: string;
    reminder_3min_message: string;
    reminder_15min_message: string;
    escalation_message: string;
    auto_reply_enabled: boolean;
    groq_api_key: string | null;
    model_chat: string;
    model_vision: string;
    reminder_3min_seconds: number;
    reminder_15min_seconds: number;
}

export interface BusinessHoursForm {
    open: string;
    close: string;
}

export interface SocialNetworksForm {
    instagram: string;
    facebook: string;
    tiktok: string;
}

export interface CompanySettingsForm {
    id?: number;
    company_name: string;
    yape_number: string;
    yape_name: string;
    business_hours: BusinessHoursForm;
    social_networks: SocialNetworksForm;
    address: string;
    sales_tone: string;
    sales_closing_cta: string;
}

export const defaultCompanySettingsForm = (): CompanySettingsForm => ({
    company_name: '',
    yape_number: '',
    yape_name: '',
    business_hours: { open: '09:00', close: '20:00' },
    social_networks: { instagram: '', facebook: '', tiktok: '' },
    address: '',
    sales_tone: 'cálido y cercano',
    sales_closing_cta: '¿Te lo separo ahora?',
});
