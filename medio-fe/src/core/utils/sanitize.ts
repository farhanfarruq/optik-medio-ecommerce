import DOMPurify from 'dompurify';

export const sanitizeHtml = (html: string | null | undefined) => {
  if (!html) return '';
  return DOMPurify.sanitize(html);
};
