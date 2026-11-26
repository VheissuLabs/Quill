export type DocumentItem = {
    id: number;
    name: string;
    contentType: string;
    size: number;
    isImage: boolean;
    uploadedBy: string | null;
    uploadedAt: string | null;
    url: string;
};
