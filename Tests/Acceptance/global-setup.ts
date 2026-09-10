import { type FullConfig, request } from '@playwright/test';

/**
 * Waits until the instance answers.
 *
 * The web server is started as a container of its own right before the suite,
 * and a PHP built-in server accepts connections a moment after its process
 * exists. Waiting here keeps that race out of the first spec.
 *
 * Through Playwright's own request context, which honours "ignoreHTTPSErrors"
 * like the browser does - a DDEV instance is served with a certificate Node
 * does not trust.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
    const baseURL = String(config.projects[0].use.baseURL);
    const context = await request.newContext({ baseURL, ignoreHTTPSErrors: true });
    const deadline = Date.now() + 60_000;
    let lastError = '';

    try {
        while (Date.now() < deadline) {
            try {
                const response = await context.get('/');
                if (response.status() === 200) {
                    return;
                }
                lastError = `HTTP ${response.status()}`;
            } catch (error) {
                lastError = String(error);
            }
            await new Promise((resolve) => setTimeout(resolve, 1_000));
        }
    } finally {
        await context.dispose();
    }

    throw new Error(`The instance at ${baseURL} did not answer with 200 within 60 seconds: ${lastError}`);
}
