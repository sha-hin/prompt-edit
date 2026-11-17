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

export type SpendUser = SpendFigures & {
    id: number | null;
    name: string;
    email: string | null;
};

export type SpendReport = {
    start: string;
    end: string;
    pixelforge_available: boolean;
    total: SpendFigures & {
        margin: string | null;
        pixelforge: PixelforgeComparison | null;
    };
    days: SpendDay[];
    users: SpendUser[];
};
