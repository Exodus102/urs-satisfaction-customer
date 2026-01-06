import sys
import joblib
import re
import nltk
from nltk.corpus import stopwords
from nltk.tokenize import word_tokenize
import numpy as np

# Make sure you have the necessary NLTK data downloaded (run this in your Colab notebook if you haven't)
# nltk.download('punkt', quiet=True)
# nltk.download('stopwords', quiet=True)
# nltk.download('punkt_tab', quiet=True) # Ensure punkt_tab is downloaded if needed for Tagalog

# Function to load stop words from a file
def load_stopwords(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            stopwords = set(line.strip() for line in f if line.strip())
        return stopwords
    except FileNotFoundError:
        print(f"Error: Stop words file not found at {filepath}. Using an empty set of stop words.")
        return set()

# Load Tagalog stop words from the same file used during training
# Make sure to provide the full path to your tagalog_stopwords.txt file
tagalog_stop_words_file = '/content/drive/MyDrive/Datasets/tagalog_stopwords.txt' # <--- Update this path
tagalog_stop_words = load_stopwords(tagalog_stop_words_file)


def preprocess_text(text):
    """
    Cleans and preprocesses a single text string using Tagalog stop words.
    """
    # Ensure text is a string, handle potential NaN values
    if not isinstance(text, str):
        return ""
    # Remove HTML tags
    text = re.sub('<[^>]*>', '', text)
    # Remove special characters and numbers, and convert to lowercase
    text = re.sub('[^a-zA-Z]', ' ', text).lower()
    # Tokenize the text
    tokens = word_tokenize(text)
    # Remove stopwords (using loaded Tagalog stop words)
    filtered_tokens = [word for word in tokens if word not in tagalog_stop_words]
    # Join the tokens back into a string
    return ' '.join(filtered_tokens)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Please provide the text to analyze as a command-line argument.")
        sys.exit(1)

    input_text = sys.argv[1]

    # Load the trained model and vectorizer
    try:
        trained_model = joblib.load('sentiment_model.joblib')
        trained_vectorizer = joblib.load('tfidf_vectorizer.joblib')
    except FileNotFoundError:
        print("Error: Model or vectorizer file not found. Make sure 'sentiment_model.joblib' and 'tfidf_vectorizer.joblib' are in the same directory.")
        sys.exit(1)

    # Preprocess the input text
    clean_input_text = preprocess_text(input_text)

    # Vectorize the preprocessed text
    input_text_vec = trained_vectorizer.transform([clean_input_text])

    # Make a prediction and get probabilities
    prediction = trained_model.predict(input_text_vec)
    probabilities = trained_model.predict_proba(input_text_vec)[0]

    # Get the predicted class label and its probability
    predicted_class = prediction[0]
    class_labels = trained_model.classes_
    predicted_proba = probabilities[np.where(class_labels == predicted_class)][0]

    # Print the prediction and confidence
    print(f"Predicted sentiment: {predicted_class}")
    print(f"Confidence: {predicted_proba:.4f}")
