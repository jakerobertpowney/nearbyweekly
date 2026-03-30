export type User = {
    id: number;
    name: string;
    email: string;
    postcode?: string | null;
    latitude?: number | null;
    longitude?: number | null;
    radius_miles?: number | null;
    newsletter_enabled?: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Interest = {
    id: number;
    name: string;
    slug: string;
};

export type InterestGroup = {
    id: number;
    name: string;
    slug: string;
    emoji: string;
    children: Interest[];
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
