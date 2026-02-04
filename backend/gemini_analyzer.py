import os
import google.generativeai as genai
from dotenv import load_dotenv

load_dotenv()

genai.configure(api_key=os.getenv("GEMINI_API_KEY"))

model = genai.GenerativeModel('gemini-flash-lite-latest') # Specified by USER

def analyze_match_with_gemini(intelligence_json):
    prompt = f"""
    Sei un esperto scommettitore professionista ed analista di dati calcistici.
    Analizza i seguenti dati JSON di una partita di calcio live in tempo reale.

    DATI PARTITA (JSON):
    {intelligence_json}

    ISTRUZIONI:
    1. Analizza l'inerzia della partita dai dati statistici (tiri, attacchi pericolosi, possesso).
    2. Considera il contesto (classifica, forma recente, precedenti H2H).
    3. Confronta le quote pre-match con la situazione attuale per trovare discrepanze.
    4. LA TUA RISPOSTA DEVE ESSERE INTERAMENTE IN LINGUA ITALIANA.

    FORMATO RISPOSTA:
    - Scrivi un'analisi Markdown in ITALIANO che spieghi il perché del tuo consiglio.
    - Alla fine, aggiungi OBBLIGATORIAMENTE un blocco JSON per il simulatore:
    ```json
    {{
      "advice": "Esito consigliato",
      "market": "Mercato (es. 1X2 o Over 2.5)",
      "odds": 2.10,
      "stake": 2.5,
      "urgency": "High/Medium/Low"
    }}
    ```
    """

    try:
        # Configuration for better results and no truncation
        config = {
            "temperature": 0.3,
            "top_p": 0.95,
            "top_k": 40,
            "max_output_tokens": 4096,
        }
        response = model.generate_content(prompt, generation_config=config)
        return response.text
    except Exception as e:
        return f"Errore durante l'analisi con Gemini: {str(e)}"
