import type { Generation } from './generations';

/** Exact 9-decimal amount, 4-decimal display text and the full figure for tooltips. */
export type ReportMoney = {
    amount: string;
    display: string;
    exact: string;
};

export type PixelforgeComparison = {
    succeeded: number;
    cost: ReportMoney;
    difference: ReportMoney;
    matches: boolean;
};

export type SpendFigures = {
    succeeded: number;
    failed: number;
    spend: ReportMoney;
    cost: ReportMoney;
    profit: ReportMoney;
};

export type SpendDay = SpendFigures & {
    date: string;
    pixelforge: PixelforgeComparison | null;
};

export type SpendReport = {
    start: string;
    end: string;
    pixelforge_available: boolean;
    total: SpendFigures & {
        margin: string | null;
        pixelforge: PixelforgeComparison | null;
    };
    credits_sold: ReportMoney;
    credits_granted: ReportMoney;
    days: SpendDay[];
};

export type AdminUserRow = SpendFigures & {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    joined_at: string | null;
    balance: ReportMoney;
};

export type UsersReportFilters = {
    start: string;
    end: string;
    search: string | null;
    sort: 'spend' | 'joined' | 'balance';
};

export type UserReport = {
    start: string;
    end: string;
    user: {
        id: number;
        name: string;
        email: string;
        is_admin: boolean;
        joined_at: string | null;
        balance: ReportMoney;
        purchased: ReportMoney;
        granted: ReportMoney;
    };
    total: SpendFigures & { margin: string | null };
    days: (SpendFigures & { date: string })[];
};

export type AdminGeneration = Generation & {
    cost: ReportMoney | null;
    profit: ReportMoney | null;
};

export type LedgerEntry = {
    id: number;
    type: 'grant' | 'purchase' | 'charge';
    amount: ReportMoney;
    balance_after: ReportMoney;
    generation_id: string | null;
    created_at: string | null;
};
