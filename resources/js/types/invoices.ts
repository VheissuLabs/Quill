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
    issueDate: string;
    dueDate: string;
    total: string;
    amountDue: string;
    status: InvoiceStatus;
    statusLabel: string;
};

export type InvoiceLine = {
    id: number;
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
    sprintId: number | null;
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
    notes: string | null;
    terms: string | null;
    sentAt: string | null;
    voidedAt: string | null;
    isEditable: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    lines: InvoiceLine[];
    payments: InvoicePayment[];
    options: {
        contacts: { id: number; name: string | null; email: string }[];
        projects: { id: number; name: string }[];
        sprints: { id: number; name: string }[];
        workTypes: { id: number; name: string }[];
    };
};

export type InvoiceProjectOption = {
    id: number;
    name: string;
    clientId: number | null;
};
