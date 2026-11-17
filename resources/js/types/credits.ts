import type { ReportMoney } from './reports';

/** The signed-in user's credit, shared with every page. Amounts come from the server; never do maths on them here. */
export type Credits = {
    balance: ReportMoney;
    reserved: ReportMoney;
    available: ReportMoney;
};

export type CreditPack = '5' | '10' | '25';
