import os
import google.generativeai as genai
from dotenv import load_dotenv

load_dotenv()

genai.configure(api_key=os.getenv("GEMINI_API_KEY"))

model = genai.GenerativeModel('gemini-1.5-flash-8b') # Faster and cheaper for data analysis

def analyze_match_with_gemini(intelligence_json):
    prompt = f"""
    Sei un esperto scommettitore professionista ed analista di dati calcistici.
    Analizza i seguenti dati JSON di una partita di calcio live e determina se esiste un valore nelle quote attuali.

    DATI PARTITA (JSON):
    {intelligence_json}

    ISTRUZIONI:
    1. Analizza l'inerzia della partita dai dati statistici (tiri, attacchi pericolosi, possesso).
    2. Considera il contesto (classifica, forma recente, precedenti H2H).
    3. Confronta le quote pre-match con la situazione attuale.
    4. Fornisci un verdetto sintetico e professionale.

    FORMATO RISPOSTA (Markdown):
    - **Analisi Momentum**: Breve descrizione della fase attuale.
    - **Valutazione Valore**: C'è valore nella scommessa? Perché?
    - **VERDETTO SCOMMESSA**: Mercato consigliato (es: 1X2, Over, etc), Esito e Stake consigliato (1-5%).
    - **Rischio**: Basso/Medio/Alto.
    """

    try:
        response = model.generate_content(prompt)
        return response.text
    except Exception as e:
        return f"Errore durante l'analisi con Gemini: {str(e)}"
