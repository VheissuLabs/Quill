export type InvoiceStatus =
    | 'draft'
    | 'sent'
    | 'partial'
    | 'paid'
    | 'overdue'
    | 'void';

export type AdjustmentType = 'percentage' | 'fixed';

export type InvoiceListItem = {
    id: number;
    number: string;
    clientId: number;
    clientName: string;
    clientSlug: string;
    issueDate: string;
    dueDate: string;
    total: string;
    amountDue: string;
    status: InvoiceStatus;
    statusLabel: string;
    isCurrentPeriod: boolean;
};

export type InvoiceLine = {
    id: number;
    completedAt: string | null;
    description: string;
    quantity: string;
    unit: 'hours' | 'units';
    rate: string;
    amount: string;
    workTypeId: number | null;
    issueId: number | null;
    issueReference: string | null;
    referenceUrl: string | null;
};

export type InvoicePayment = {
    id: number;
    amount: string;
    paidOn: string;
    method: string;
    methodLabel: string;
    reference: string | null;
    notes: string | null;
};

export type Invoice = InvoiceListItem & {
    projectId: number | null;
    contactId: number | null;
    ccEmails: string[];
    servicePeriodStart: string | null;
    servicePeriodEnd: string | null;
    subtotal: string;
    taxRate: string | null;
    taxAmount: string;
    discountType: AdjustmentType | null;
    discountValue: string | null;
    discountAmount: string;
    depositType: AdjustmentType | null;
    depositValue: string | null;
    depositAmount: string;
    amountPaid: string;
    publicUrl?: string;
    sentAt: string | null;
    closesAt: string | null;
    voidedAt: string | null;
    isEditable: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canSend: boolean;
    canVoid: boolean;
    canDuplicate: boolean;
    lines: InvoiceLine[];
    payments: InvoicePayment[];
    options: {
        contacts: { id: number; name: string | null; email: string }[];
        projects: { id: number; name: string; slug: string }[];
        workTypes: { id: number; name: string }[];
        paymentMethods: { value: string; label: string }[];
    };
};

export type InvoiceProjectOption = {
    id: number;
    name: string;
    billToClientId: number;
};

export type PublicInvoice = {
    number: string;
    isVoid: boolean;
    status: InvoiceStatus;
    statusLabel: string;
    isCurrentPeriod: boolean;
    organizationName: string;
    organizationLogoUrl: string | null;
    fromAddress: string[];
    clientAddress: string[];
    clientName: string;
    contactName: string | null;
    contactEmail: string | null;
    issueDate: string;
    dueDate: string;
    servicePeriodStart: string | null;
    servicePeriodEnd: string | null;
    lines: {
        completedAt: string | null;
        description: string;
        issueReference: string | null;
        referenceUrl: string | null;
        quantity: string;
        unit: 'hours' | 'units';
        rate: string;
        amount: string;
    }[];
    subtotal: string;
    taxRate: string | null;
    taxAmount: string;
    discountAmount: string;
    total: string;
    depositAmount: string;
    payments: { amount: string; paidOn: string; methodLabel: string }[];
    amountPaid: string;
    amountDue: string;
    payUrl: string;
    canPayOnline: boolean;
    paymentProcessing: boolean;
    paymentInstructions: string | null;
};

export type InvoiceMonthSummary = {
    month: string;
    total: string;
    collected: string;
    outstanding: string;
    invoices: number;
};

export type InvoiceSummary = {
    nextPayment: {
        invoiceId: number;
        number: string;
        clientName: string;
        amountDue: string;
        dueDate: string;
        isDraft: boolean;
        isOverdue: boolean;
    } | null;
    thisMonth: InvoiceMonthSummary;
    nextMonth: InvoiceMonthSummary;
};
