import {cookies,draftMode} from 'next/headers';
import {NextResponse} from 'next/server';
import {previewCookie} from '../../../../../lib/preview.mjs';
import {origin} from '../../../../../lib/content.mjs';
export async function GET(){(await draftMode()).disable();(await cookies()).delete(previewCookie);return NextResponse.redirect(origin()+'/',303);}
