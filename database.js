import express from 'express';
import bodyParser from 'body-parser';
import mysql from 'mysql2/promise';
import cors from 'cors';
import nodemailer from 'nodemailer';
import fs from 'fs';

const app = express();
app.use(cors()); // Enable CORS for all routes
app.use(bodyParser.json());

// Your MySQL SSL configuration
const sslOptions = {
    rejectUnauthorized: true, // This is equivalent to PostgreSQL's `rejectUnauthorized`
    ca: fs.readFileSync('./ca.pem').toString() // Make sure the path to ca.pem is correct
};

// Create the connection pool with SSL options
const pool = mysql.createPool({
    host: 'mysql-30f3d557-acemovers-dd24.b.aivencloud.com',
    port: 26656, // Make sure to use the port provided by your database service
    user: 'avnadmin',
    password: 'AVNS_NU9ZIgbnh6Rrvc7ThrU', // Replace with your actual password
    database: 'defaultdb',
    ssl: sslOptions
});


const emailAddresses = ['aaron@acemovers.com.au', 'harry@acemovers.com.au', 'kevin@acemovers.com.au', 'nick@acemovers.com.au'];

const transporter = nodemailer.createTransport({
    host: "smtp.elasticemail.com",
    port: 587,
    secure: false,
    auth: {
        user: 'aaron@acemovers.com.au',
        pass: '8F1E23DEE343B60A0336456A6944E7B4F7DA',
    },
});

// Function to send email
const sendEmail = async (formData, nextEmailAddress) => {
    let emailBody = `Name: ${formData.Name}\nBedrooms: ${formData.Bedrooms}\nPickup: ${formData.Pickup}\nDropoff: ${formData.Dropoff}\nDate: ${formData.Date}\nPhone number: ${formData.Phone}\nEmail: ${formData.Email}\nDetails: ${formData.Details}`;

    let mailOptions = {
        from: 'aaron@acemovers.com.au',
        to: nextEmailAddress,
        subject: "New Lead",
        text: emailBody,
    };

    try {
        await transporter.sendMail(mailOptions);
        console.log('Email sent to ' + nextEmailAddress);
    } catch (error) {
        console.error('Error sending email: ', error);
        throw error; // Re-throw the error to be caught by the caller
    }
};

// Function to get the next email index and count
const getNextEmailInfo = async () => {
    const [rows] = await pool.query('SELECT current_index, count FROM email_tracker WHERE id = 1');
    return rows[0];
};

// Function to update the email index and count
const updateEmailInfo = async (index, count) => {
    await pool.query('UPDATE email_tracker SET current_index = ?, count = ? WHERE id = 1', [index, count]);
};

// End point for form submitting to database
app.post('/submit-form', async (req, res) => {
    const { Name, Bedrooms, Pickup, Dropoff, Date, Phone, Email, Details } = req.body;
    const values = [Name || null, Bedrooms || null, Pickup || null, Dropoff || null, Date || null, Phone || null, Email || null, Details || null];
    const query = `INSERT INTO leads (lead_name, bedrooms, pickup, dropoff, lead_date, phone, email, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?);`;

    try {
        await pool.query(query, values);

        const { current_index, count } = await getNextEmailInfo();

        await sendEmail(req.body, emailAddresses[current_index]);

        const newCount = count + 1;
        const maxEmailsPerAddress = 3; // This can be adjusted as needed
        let newIndex = current_index;

        if (newCount >= maxEmailsPerAddress) {
            newIndex = (current_index + 1) % emailAddresses.length;
            await updateEmailInfo(newIndex, 0); // Reset count for the new index
        } else {
            await updateEmailInfo(current_index, newCount); // Increment count for the current index
        }

        res.send('Form data saved and email sent successfully!');
    } catch (error) {
        console.error('Error processing form submission and sending email: ', error);
        res.status(500).send('Error processing request');
    }
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});