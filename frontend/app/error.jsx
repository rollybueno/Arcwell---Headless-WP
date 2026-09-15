'use client';
export default function ErrorPage({reset}) {return <main id="main" className="wrap error-panel"><span className="eyebrow accent">A short pause</span><h1>The journal is temporarily unavailable.</h1><p>We couldn’t load the latest stories. Please try again in a moment.</p><button className="button" onClick={reset}>Try again ↗</button></main>;}
