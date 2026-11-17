export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type ImageSize = '1024x1024' | '1536x1024' | '1024x1536';

export type ImageQuality = 'low' | 'medium' | 'high';

/** Money arrives from the server as exact 9-decimal strings plus display text. Never do maths on it here. */
export type MoneyRange = {
    min: string;
    max: string;
    display: string;
};

export type Generation = {
    id: string;
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    reference_images: string[];
    status: GenerationStatus;
    estimate: MoneyRange;
    charge: { amount: string; display: string; exact: string } | null;
    error_message: string | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    created_at: string;
    completed_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
};

export type CostEstimate = {
    charge: MoneyRange;
};
